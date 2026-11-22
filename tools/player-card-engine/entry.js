// Build with build.cjs. Skin geometry, UVs and Alex/Steve detection belong to skinview3d.
import { SkinObject } from 'skinview3d/libs/model.js';
import { loadSkinToCanvas, inferModelType } from 'skinview-utils';
import { AmbientLight, Box3, BufferGeometry, CanvasTexture, DirectionalLight, DoubleSide, Float32BufferAttribute, Group, Mesh, MeshStandardMaterial, NearestFilter, PerspectiveCamera, Scene, SRGBColorSpace, Vector3, WebGLRenderer } from 'three';

let engine, unavailable = false;
function initialize() {
  const canvas = document.createElement('canvas');
  const renderer = new WebGLRenderer({canvas, alpha:true, antialias:true, preserveDrawingBuffer:true});
  renderer.setPixelRatio(1);
  renderer.setClearColor(0, 0);
  const scene = new Scene(), skin = new SkinObject(), camera = new PerspectiveCamera(36, 1, .1, 1000);
  scene.add(skin, new AmbientLight(0xffffff, 3));
  const light = new DirectionalLight(0xffffff, .65); light.position.set(-3, 8, 5); scene.add(light);
  return {canvas, renderer, scene, skin, camera, rest:new Map([...skin.getBodyParts()].map(part=>[part,part.position.clone()])), skins:new Map()};
}
function texture(image) {
  const t = new CanvasTexture(image);
  t.magFilter = t.minFilter = NearestFilter; t.generateMipmaps = false; t.colorSpace = SRGBColorSpace;
  return t;
}
function horseObject(faces) {
  const group = new Group(), positions = [], uvs = [], image = faces[0].texture;
  for (const face of faces) {
    const [u0,v0,u1,v1] = face.rect, uv = [[u1,v0],[u0,v0],[u0,v1],[u1,v1]];
    for (const i of [0,1,2,0,2,3]) {
      const [x,y,z] = face.points[i]; positions.push(x,-y,-z);
      uvs.push(uv[i][0]/image.width, 1-uv[i][1]/image.height);
    }
  }
  const geometry = new BufferGeometry();
  geometry.setAttribute('position', new Float32BufferAttribute(positions,3));
  geometry.setAttribute('uv', new Float32BufferAttribute(uvs,2)); geometry.computeVertexNormals();
  const map = texture(image), material = new MeshStandardMaterial({map, side:DoubleSide, transparent:true, alphaTest:.05, roughness:1});
  group.add(new Mesh(geometry,material));
  group.dispose = () => {geometry.dispose(); material.dispose(); map.dispose();};
  return group;
}
function drawScene(ctx, {atlas=null, horseFaces=[], pose='standing', area}) {
  if (unavailable || (!atlas && !horseFaces.length)) return false;
  let horse;
  try {
    engine ??= initialize();
    const {canvas,renderer,scene,skin,camera,skins,rest} = engine;
    if (renderer.getContext().isContextLost()) return false;
    renderer.setSize(Math.max(1,Math.ceil(area.w)),Math.max(1,Math.ceil(area.h)),false);
    skin.visible = !!atlas && pose !== 'horse';
    if (skin.visible) {
      if (!skins.has(atlas)) {
        const image = document.createElement('canvas'); loadSkinToCanvas(image,atlas);
        skins.set(atlas,{map:texture(image),model:inferModelType(image)});
        if(skins.size>8){const key=skins.keys().next().value;skins.get(key).map.dispose();skins.delete(key);}
      }
      const source = skins.get(atlas); skin.map = source.map; skin.modelType = source.model;
      skin.rotation.set(0,0,0); skin.position.set(0,pose==='riding'?34.5:24,pose==='riding'?-1:0);
      for (const part of skin.getBodyParts()) {part.position.copy(rest.get(part));part.rotation.set(0,0,0);part.visible=pose!=='head'||part===skin.head;}
      if (pose === 'riding') {
        skin.head.rotation.set(.28,.70,0);
        skin.rightArm.rotation.set(-.65,0,.04); skin.leftArm.rotation.set(-.65,0,-.04);
        // The horse is ten units wide: knees must clear its flanks before descending.
        skin.rightLeg.position.x=-3.2;skin.leftLeg.position.x=3.2;
        skin.rightLeg.rotation.set(-1.05,0,-.48);
        skin.leftLeg.rotation.set(-1.05,0,.48);
      }
    }
    if(horseFaces.length){horse=horseObject(horseFaces);scene.add(horse);}
    scene.updateMatrixWorld(true);
    // Visible meshes only: hidden limbs must not shrink a head-only portrait.
    const bounds = new Box3(), vertices=[];
    for(const root of [skin,horse].filter(Boolean)) root.traverseVisible(object=>{
      if(object.isMesh){
        object.geometry.computeBoundingBox();bounds.union(object.geometry.boundingBox.clone().applyMatrix4(object.matrixWorld));
        const positions=object.geometry.getAttribute('position');
        for(let i=0;i<positions.count;i++)vertices.push(new Vector3().fromBufferAttribute(positions,i).applyMatrix4(object.matrixWorld));
      }
    });
    const center=bounds.getCenter(new Vector3()),size=bounds.getSize(new Vector3());
    const yaw=pose==='riding'||pose==='horse'?.72:.26,elevation=pose==='riding'?.18:.08;
    const direction=new Vector3(Math.sin(yaw)*Math.cos(elevation),Math.sin(elevation),Math.cos(yaw)*Math.cos(elevation));
    camera.aspect=canvas.width/canvas.height;
    camera.position.copy(center).addScaledVector(direction,Math.max(size.length(),1)*3);
    camera.lookAt(center); camera.updateMatrixWorld(true); camera.updateProjectionMatrix();
    // Fit actual geometry, including raised knees; empty bounding-box corners must not shrink the portrait.
    let distance=0;
    const tan=Math.tan(camera.fov*Math.PI/360), inverse=camera.matrixWorldInverse;
    for(const vertex of vertices) {
      const relative=vertex.clone().sub(center),depth=relative.dot(direction);
      const corner=vertex.clone().applyMatrix4(inverse);
      distance=Math.max(distance,Math.abs(corner.x)/(tan*camera.aspect)+depth,Math.abs(corner.y)/tan+depth);
    }
    camera.position.copy(center).addScaledVector(direction,Math.max(distance*1.07,1));camera.lookAt(center);camera.updateMatrixWorld(true);
    renderer.render(scene,camera);
    ctx.save();ctx.imageSmoothingEnabled=true;ctx.drawImage(canvas,area.x,area.y,area.w,area.h);ctx.restore();
    return true;
  } catch {unavailable=true;return false;}
  finally {if(horse){engine.scene.remove(horse);horse.dispose();}}
}
if(window.PlayerModelRenderer?.revision!=='rider-2')window.PlayerModelRenderer = {drawScene, revision:'rider-2', version:'skinview3d 3.4.2 / Three.js 0.156.1'};
