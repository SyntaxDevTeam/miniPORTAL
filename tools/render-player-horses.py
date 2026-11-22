#!/usr/bin/env python3
"""Offline vanilla horse previews. Requires Pillow and NumPy; never used by HTTP.

Usage: python3 tools/render-player-horses.py /path/to/26.2-client.jar
The pinned official client is verified before reading its eleven horse textures.
Only a standing adult horse's coat/markings are rendered, without equipment.
"""
import hashlib
import io
import json
import math
from pathlib import Path
import sys
import zipfile

import numpy as np
from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
DEST = ROOT / 'modules/PlayerProfile/assets/horses'
SHA1 = '2dc72797acbc1b63fc16a11c4ac393605f453754'
CLIENT = f'https://piston-data.mojang.com/v1/objects/{SHA1}/client.jar'
COLORS = ['white', 'creamy', 'chestnut', 'brown', 'black', 'gray', 'darkbrown']
STYLES = {'none': None, 'white': 'white', 'whitefield': 'whitefield',
          'white_dots': 'whitedots', 'black_dots': 'blackdots'}


def rotation(angle):
    c, s = math.cos(angle), math.sin(angle)
    return np.array([[1, 0, 0], [0, c, -s], [0, s, c]])


def geometry():
    # Adult AbstractEquineModel mesh and box UV coordinates in the pinned client.
    # box: origin, size, UV origin, pivot, X rotation, mirror, inflation.
    return [
        ((-5, -8, -17), (10, 10, 22), (0, 32), (0, 11, 5), 0, False, .05),
        ((-2.05, -6, -2), (4, 12, 7), (0, 35), (0, 4, -12), math.pi/6, False, 0),
        ((-3, -11, -2), (6, 5, 7), (0, 13), (0, 4, -12), math.pi/6, False, 0),
        ((-1, -11, 5.01), (2, 16, 2), (56, 36), (0, 4, -12), math.pi/6, False, 0),
        ((-2, -11, -7), (4, 5, 5), (0, 25), (0, 4, -12), math.pi/6, False, 0),
        ((-3, -1.01, -1), (4, 11, 4), (48, 21), (4, 14, 7), 0, True, 0),
        ((-1, -1.01, -1), (4, 11, 4), (48, 21), (-4, 14, 7), 0, False, 0),
        ((-3, -1.01, -1.9), (4, 11, 4), (48, 21), (4, 14, -10), 0, True, 0),
        ((-1, -1.01, -1.9), (4, 11, 4), (48, 21), (-4, 14, -10), 0, False, 0),
        ((-1.5, 0, 0), (3, 14, 4), (42, 36), (0, 6, 7), math.pi/6, False, 0),
        ((.55, -13, 4), (2, 3, 1), (19, 16), (0, 4, -12), math.pi/6, False, -.001),
        ((-2.55, -13, 4), (2, 3, 1), (19, 16), (0, 4, -12), math.pi/6, False, -.001),
    ]


def triangles():
    yaw, elevation = math.radians(53), math.radians(12)
    right = np.array([math.cos(yaw), 0, math.sin(yaw)])
    view = np.array([math.sin(yaw)*math.cos(elevation), -math.sin(elevation),
                     -math.cos(yaw)*math.cos(elevation)])
    down = np.cross(right, view)
    camera = np.array([right, down, view])
    result = []
    for origin, size, (u, v), pivot, angle, mirror, inflate in geometry():
        w, h, d = size
        x, y, z = origin
        x0, x1 = x-inflate, x+w+inflate
        if mirror:
            x0, x1 = x1, x0
        y0, y1, z0, z1 = y-inflate, y+h+inflate, z-inflate, z+d+inflate
        vertices = np.array([(x0,y0,z0),(x1,y0,z0),(x1,y1,z0),(x0,y1,z0),
                             (x0,y0,z1),(x1,y0,z1),(x1,y1,z1),(x0,y1,z1)])
        vertices = vertices @ rotation(angle).T + pivot
        faces = [
            ([5,4,0,1], (u+d,v,u+d+w,v+d)),
            ([2,3,7,6], (u+d+w,v+d,u+d+2*w,v)),
            ([0,4,7,3], (u,v+d,u+d,v+d+h)),
            ([1,0,3,2], (u+d,v+d,u+d+w,v+d+h)),
            ([5,1,2,6], (u+d+w,v+d,u+2*d+w,v+d+h)),
            ([4,5,6,7], (u+2*d+w,v+d,u+2*d+2*w,v+d+h)),
        ]
        for indices, (u0,v0,u1,v1) in faces:
            pts = vertices[indices]
            normal = np.cross(pts[1]-pts[0], pts[2]-pts[0])
            normal /= np.linalg.norm(normal)
            # Two soft directional lights preserve the original pixel artwork.
            shade = .65 + .22*abs(normal[1]) + .13*max(0, normal @ view)
            uv = np.array([(u1,v0),(u0,v0),(u0,v1),(u1,v1)])
            for ids in ([0,1,2], [0,2,3]):
                result.append((pts[ids] @ camera.T, uv[ids], shade))
    return result


def render(texture, mesh):
    width, height = 480, 400
    canvas = np.zeros((height, width, 4), dtype=np.uint8)
    depth = np.full((height, width), -np.inf)
    for points, uv, shade in mesh:
        p = points.copy()
        p[:,0] = p[:,0]*9 + width/2
        p[:,1] = p[:,1]*9 + 140
        lo = np.maximum(np.floor(p[:,:2].min(axis=0)).astype(int), (0,0))
        hi = np.minimum(np.ceil(p[:,:2].max(axis=0)).astype(int), (width-1,height-1))
        if np.any(hi < lo):
            continue
        xx, yy = np.meshgrid(np.arange(lo[0],hi[0]+1)+.5, np.arange(lo[1],hi[1]+1)+.5)
        a, b, c = p
        denominator = (b[1]-c[1])*(a[0]-c[0])+(c[0]-b[0])*(a[1]-c[1])
        if abs(denominator) < 1e-8:
            continue
        wa = ((b[1]-c[1])*(xx-c[0])+(c[0]-b[0])*(yy-c[1]))/denominator
        wb = ((c[1]-a[1])*(xx-c[0])+(a[0]-c[0])*(yy-c[1]))/denominator
        wc = 1-wa-wb
        coords = wa[...,None]*uv[0]+wb[...,None]*uv[1]+wc[...,None]*uv[2]
        tx = np.clip(np.floor(coords[...,0]).astype(int),0,texture.shape[1]-1)
        ty = np.clip(np.floor(coords[...,1]).astype(int),0,texture.shape[0]-1)
        pixels = texture[ty,tx].copy()
        pixels[...,:3] = np.rint(pixels[...,:3]*shade).astype(np.uint8)
        zz = wa*a[2]+wb*b[2]+wc*c[2]
        region = np.s_[lo[1]:hi[1]+1,lo[0]:hi[0]+1]
        mask = (wa>=-1e-6)&(wb>=-1e-6)&(wc>=-1e-6)&(zz>depth[region])&(pixels[...,3]>0)
        canvas[region][mask] = pixels[mask]
        depth[region][mask] = zz[mask]
    return Image.fromarray(canvas).resize((360,300),Image.Resampling.LANCZOS)


def main():
    client = Path(sys.argv[1]).read_bytes()
    if hashlib.sha1(client).hexdigest() != SHA1:
        raise SystemExit('Expected the official Minecraft Java 26.2 client (SHA-1 mismatch).')
    DEST.mkdir(parents=True,exist_ok=True)
    source = {'minecraft_version':'26.2','client_url':CLIENT,'client_sha1':SHA1,
              'copyright':'Minecraft textures © Mojang AB / Microsoft. Not covered by the portal license.',
              'scope':'Standing adult horse coat and markings; no saddle, armour or animation.', 'textures':{}}
    with zipfile.ZipFile(io.BytesIO(client)) as archive:
        for name in ['horse_'+c for c in COLORS]+['horse_markings_'+s for s in STYLES.values() if s]:
            path = 'assets/minecraft/textures/entity/horse/'+name+'.png'
            raw = archive.read(path)
            image = Image.open(io.BytesIO(raw))
            if image.size != (64,64) or image.format != 'PNG':
                raise SystemExit('Unexpected horse texture: '+path)
            (DEST/(name+'.png')).write_bytes(raw)
            source['textures'][name] = {'path':path,'sha256':hashlib.sha256(raw).hexdigest()}
    mesh = triangles()
    for color in COLORS:
        base = Image.open(DEST/('horse_'+color+'.png')).convert('RGBA')
        for style, suffix in STYLES.items():
            texture = base.copy()
            if suffix:
                texture.alpha_composite(Image.open(DEST/('horse_markings_'+suffix+'.png')).convert('RGBA'))
            render(np.array(texture),mesh).save(DEST/(color+'_'+style+'.png'),optimize=True)
    (DEST/'source.json').write_text(json.dumps(source,indent=2,ensure_ascii=False)+'\n')
    print('Imported 11 original textures and rendered all 35 horse variants.')


if __name__ == '__main__':
    main()
