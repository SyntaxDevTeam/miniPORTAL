/* Local, static player-card renderer. The visible canvas is the PNG export. */
(() => {
  'use strict';
  const VERSION='0.21.0';
  if (window.PlayerShowcase?.version===VERSION) { window.PlayerShowcase.boot(); return; }
  if(window.PlayerShowcase?.boot){
    document.removeEventListener('DOMContentLoaded',window.PlayerShowcase.boot);
    document.removeEventListener('htmx:afterSwap',window.PlayerShowcase.boot);
  }
  function replaceControls(node,selector){node.querySelectorAll(selector).forEach(element=>element.replaceWith(element.cloneNode(true)));}
  const FORMATS = {landscape:[1920,1080],square:[1080,1080],portrait:[1080,1920]};
  const PALETTES = {moon:['#120d23','#38204f','#dfb8f7','#f9f0ff'],aurora:['#071b22','#154851','#82e4ce','#effffb'],sunset:['#261222','#683240','#ffc0a2','#fff4ed']};
  const images = new Map();
  function load(url) {
    if (!url || (!/^\/(?!\/)[a-zA-Z0-9_/?=&%.:-]+$/.test(url)&&!/^data:image\/(?:webp|png);base64,[a-zA-Z0-9+/=]+$/.test(url))) return Promise.resolve(null);
    if(url.startsWith('data:'))for(const key of images.keys())if(key.startsWith('data:')&&key!==url)images.delete(key);
    if (!images.has(url)) {
      const promise = new Promise(resolve => { const i=new Image(); i.onload=()=>resolve(i); i.onerror=()=>{images.delete(url);resolve(null);}; i.src=url; });
      images.set(url,promise); if(images.size>100)images.delete(images.keys().next().value);
    }
    return images.get(url);
  }
  const add=(a,b)=>a.map((v,i)=>v+b[i]);
  function rotate(p,rx=0,ry=0,rz=0) {
    let [x,y,z]=p,c=Math.cos(rx),s=Math.sin(rx); [y,z]=[y*c-z*s,y*s+z*c];
    c=Math.cos(ry);s=Math.sin(ry);[x,z]=[x*c+z*s,-x*s+z*c];
    c=Math.cos(rz);s=Math.sin(rz);return [x*c-y*s,x*s+y*c,z];
  }
  function box(mesh,origin,size,uv,transform,texture,inflate=0,mirror=false) {
    const [x,y,z]=origin,[w,h,d]=size,[u,v]=uv;
    let a=x-inflate,b=x+w+inflate; if(mirror)[a,b]=[b,a];
    const c=y-inflate,e=y+h+inflate,f=z-inflate,g=z+d+inflate;
    const vertices=[[a,c,f],[b,c,f],[b,e,f],[a,e,f],[a,c,g],[b,c,g],[b,e,g],[a,e,g]].map(transform);
    const faces=[[[5,4,0,1],[u+d,v,u+d+w,v+d]],[[2,3,7,6],[u+d+w,v+d,u+d+2*w,v]],[[0,4,7,3],[u,v+d,u+d,v+d+h]],[[1,0,3,2],[u+d,v+d,u+d+w,v+d+h]],[[5,1,2,6],[u+d+w,v+d,u+2*d+w,v+d+h]],[[4,5,6,7],[u+2*d+w,v+d,u+2*d+2*w,v+d+h]]];
    faces.forEach(([ids,rect])=>mesh.push({points:ids.map(i=>vertices[i]),rect,texture}));
  }
  function drawFront(ctx,image,pose,area) {
    const head=pose==='head',sw=head?64:128,sh=head?64:256;
    let scale=Math.min(area.w/sw,area.h/sh);if(scale>=1)scale=Math.floor(scale);
    const w=sw*scale,h=sh*scale;
    ctx.imageSmoothingEnabled=false;
    ctx.drawImage(image,head?32:0,0,sw,sh,Math.round(area.x+(area.w-w)/2),Math.round(area.y+(area.h-h)/2),w,h);
  }
  function horse(mesh,texture,offset=[0,0,0],scale=1) {
    const parts=[
      [[-5,-8,-17],[10,10,22],[0,32],[0,11,5],0,false,.05],
      [[-2.05,-6,-2],[4,12,7],[0,35],[0,4,-12],Math.PI/6],
      [[-3,-11,-2],[6,5,7],[0,13],[0,4,-12],Math.PI/6],
      [[-1,-11,5.01],[2,16,2],[56,36],[0,4,-12],Math.PI/6],
      [[-2,-11,-7],[4,5,5],[0,25],[0,4,-12],Math.PI/6],
      [[-3,-1.01,-1],[4,11,4],[48,21],[4,14,7],0,true],
      [[-1,-1.01,-1],[4,11,4],[48,21],[-4,14,7]],
      [[-3,-1.01,-1.9],[4,11,4],[48,21],[4,14,-10],0,true],
      [[-1,-1.01,-1.9],[4,11,4],[48,21],[-4,14,-10]],
      [[-1.5,0,0],[3,14,4],[42,36],[0,6,7],Math.PI/6],
      [[.55,-13,4],[2,3,1],[19,16],[0,4,-12],Math.PI/6,false,-.001],
      [[-2.55,-13,4],[2,3,1],[19,16],[0,4,-12],Math.PI/6,false,-.001]
    ];
    parts.forEach(([o,s,uv,pivot,rx=0,mirror=false,inflate=0])=>box(mesh,o,s,uv,p=>add(add(rotate(p,rx),pivot).map((v,i)=>(v-(i===1?24:0))*scale),offset),texture,inflate,mirror));
  }
  function round(ctx,x,y,w,h,r=24){ctx.beginPath();ctx.roundRect(x,y,w,h,r);}
  function text(ctx,value,x,y,size,color,maxWidth,weight=500) {
    ctx.font=`${weight} ${size}px system-ui, sans-serif`;ctx.fillStyle=color;
    let label=String(value??'');let fitted=size;
    while(ctx.measureText(label).width>maxWidth&&fitted>size*.65){fitted--;ctx.font=`${weight} ${fitted}px system-ui, sans-serif`;}while(ctx.measureText(label).width>maxWidth&&label.length>1)label=label.slice(0,-2)+'…';
    ctx.fillText(label,x,y);return label;
  }
  function wrap(ctx,value,x,y,size,color,maxWidth,maxLines=3) {
    ctx.font=`400 ${size}px system-ui, sans-serif`;ctx.fillStyle=color;
    const words=String(value||'').split(/\s+/);let line='',count=0;
    for(const word of words){const next=(line?line+' ':'')+word;if(ctx.measureText(next).width>maxWidth&&line){text(ctx,line,x,y+count*size*1.4,size,color,maxWidth,400);count++;line=word;if(count===maxLines-1)break;}else line=next;}
    if(line)text(ctx,line,x,y+count*size*1.4,size,color,maxWidth,400);
  }
  const FILTERS={
    moon:'saturate(0.88) contrast(1.12) brightness(0.92)',
    aurora:'grayscale(0.8) sepia(0.65) hue-rotate(135deg) saturate(1.45) contrast(1.1) brightness(0.93)',
    sunset:'grayscale(0.35) sepia(0.65) hue-rotate(335deg) saturate(1.65) contrast(1.08) brightness(0.98)'
  };
  function cover(ctx,image,w,h,design={}){
    const scale=Math.max(w/image.width,h/image.height)*(design.zoom||100)/100;
    ctx.drawImage(image,(w-image.width*scale)*(design.crop_x??50)/100,(h-image.height*scale)*(design.crop_y??50)/100,image.width*scale,image.height*scale);
  }
  function background(ctx,w,h,p,artwork,left,palette,design) {
    ctx.fillStyle=p[0];ctx.fillRect(0,0,w,h);
    if(artwork){
      ctx.save();ctx.filter=FILTERS[palette]||FILTERS.moon;cover(ctx,artwork,w,h,design);ctx.restore();
      // Only the scenery is blurred. Text, the original skin and the horse stay sharp.
      const blur=document.createElement('canvas');blur.width=Math.ceil(w/2);blur.height=Math.ceil(h/2);
      const g=blur.getContext('2d');g.filter=(FILTERS[palette]||FILTERS.moon)+' blur('+Math.round(Math.min(w,h)*.012*(design.blur??45)/45)+'px)';cover(g,artwork,blur.width,blur.height,design);g.filter='none';
      g.globalCompositeOperation='destination-in';const mask=g.createLinearGradient(0,blur.height*.78,0,blur.height);mask.addColorStop(0,'#0000');mask.addColorStop(1,'#000');g.fillStyle=mask;g.fillRect(0,0,blur.width,blur.height);
      ctx.drawImage(blur,0,0,w,h);
      ctx.save();ctx.globalAlpha=(design.shade??35)/65;
      const shade=ctx.createLinearGradient(left?w:0,0,left?0:w,0);shade.addColorStop(0,p[0]+'d9');shade.addColorStop(.53,p[0]+'88');shade.addColorStop(1,p[0]+'20');ctx.fillStyle=shade;ctx.fillRect(0,0,w,h);
      ctx.restore();const floor=ctx.createLinearGradient(0,h*.80,0,h);floor.addColorStop(0,p[0]+'00');floor.addColorStop(.65,p[0]+'88');floor.addColorStop(1,p[0]+'f2');ctx.fillStyle=floor;ctx.fillRect(0,0,w,h);
    }
    ctx.strokeStyle=p[2]+'35';ctx.lineWidth=2;round(ctx,24,24,w-48,h-48,30);ctx.stroke();
  }
  async function assets(model){
    const [atlas,front,base,mark]=await Promise.all([load(model.texture_url),load(model.image_url),load(model.horse?.texture_url),load(model.horse?.marking_url)]);
    let horseTexture=null;if(base){horseTexture=document.createElement('canvas');horseTexture.width=horseTexture.height=64;const c=horseTexture.getContext('2d');c.drawImage(base,0,0);if(mark)c.drawImage(mark,0,0);}
    return {atlas,front,horseTexture};
  }
  function scene(ctx,source,pose,area){
    const riding=pose==='riding'&&source.atlas&&source.horseTexture,horseOnly=pose==='horse',horseFaces=[];
    const actual=horseOnly?'horse':riding?'riding':pose==='head'?'head':'standing';
    if((riding||horseOnly)&&source.horseTexture)horse(horseFaces,source.horseTexture);
    if(window.PlayerModelRenderer?.drawScene(ctx,{atlas:source.atlas,horseFaces,pose:actual,area}))return {renderer:'skinview3d',pose:actual};
    if(!horseOnly&&source.front){drawFront(ctx,source.front,actual==='head'?'head':'standing',area);return {renderer:'front2d',pose:actual==='head'?'head':'standing'};}
    return {renderer:'unavailable',pose:actual==='head'?'head':'standing'};
  }
  async function paint(canvas,model) {
    const card={...model.design,pose:model.design.pose==='running'?'standing':model.design.pose},format=FORMATS[card.format]||FORMATS.landscape,[w,h]=format,p=PALETTES[card.palette]||PALETTES.moon;
    const [source,artwork]=await Promise.all([assets(model),load(card.backdrop==='custom'?model.background_url:model.artworks?.[({stars:'forest',glow:'meadow'})[card.backdrop]||card.backdrop]||model.artwork_url)]);
    const {atlas,front,horseTexture}=source;
    canvas.width=w;canvas.height=h;const ctx=canvas.getContext('2d');background(ctx,w,h,p,artwork,card.layout==='reverse',card.palette,card);
    const tall=h>w*1.2,pad=w*.06,contentW=tall?w-pad*2:w*.5;
    const left=card.layout==='reverse',contentX=tall?pad:left?w*.44:pad;
    const sceneArea=tall?{x:left?pad:w*.5,y:h*.28,w:w*.43,h:h*.4}:{x:left?pad:w*.63,y:h*.18,w:w*.31,h:h*.56};
    text(ctx,model.brand||'Minecraft',pad,pad+16,Math.round(w*.018),p[2],w-pad*2,650);
    const titleY=tall?h*.125:h*.245;
    text(ctx,model.name,contentX,titleY,Math.round(w*(tall?.085:.056)),p[3],contentW,750);
    const ranks=(model.metrics||[]).filter(m=>m.label!=='Czas gry').map(m=>m.value).filter(Boolean).join('  ·  ');
    text(ctx,ranks,contentX,titleY+w*.038,Math.round(w*.019),p[2],contentW,600);
    const playTime=card.show_time?(model.metrics||[]).find(m=>m.label==='Czas gry')?.value:null;
    if(playTime)text(ctx,'Czas gry: '+playTime,contentX,titleY+w*.068,Math.round(w*.015),p[3]+'bb',contentW);
    wrap(ctx,model.bio,contentX,tall?h*.23:h*.43,Math.round(w*.02),p[3]+'cc',contentW,tall?2:3);
    const result=scene(ctx,source,card.pose,sceneArea);
    const statsY=tall?h*.34:h*.61,statW=tall?w*.38:(contentW-28)/3;
    (model.statistics||[]).slice(0,3).forEach((s,i)=>{
      const x=tall?(left?w*.56:pad):contentX+i*(statW+14),y=tall?statsY+i*h*.095:statsY;
      ctx.fillStyle=p[0]+'99';round(ctx,x,y,statW,h*(tall?.07:.12),16);ctx.fill();ctx.strokeStyle=p[2]+'22';ctx.lineWidth=1;ctx.stroke();
      text(ctx,s.value,x+18,y+h*(tall?.035:.058),Math.round(w*.03),p[3],statW-36,700);
      text(ctx,s.label,x+18,y+h*(tall?.054:.09),Math.round(w*.0125),p[2],statW-36);
    });
    const owned=model.horse;
    if(owned){
      const y=tall?h*.80:h*.76,hh=tall?h*.125:h*.14;
      ctx.fillStyle=p[0]+'99';round(ctx,pad,y,w-pad*2,hh,18);ctx.fill();
      const thumbArea={x:pad+16,y:y+12,w:hh*.95,h:hh-24};
      if(horseTexture)scene(ctx,source,'horse',thumbArea);
      const x=pad+hh*1.08,available=w-pad-x-20;
      text(ctx,[...new Set([owned.name,owned.breed].filter(Boolean))].join(' · '),x,y+hh*.28,Math.round(w*.022),p[3],available,650);
      const facts=card.show_horse_stats?(owned.statistics||[]).slice(0,3):[];
      facts.forEach((fact,i)=>{
        const step=available/Math.max(1,facts.length),fx=x+i*step;
        text(ctx,fact.value,fx,y+hh*.61,Math.round(w*.022),p[3],step-16,650);
        text(ctx,fact.label,fx,y+hh*.83,Math.round(w*.013),p[2],step-16);
      });
    }
    const stamp=model.updated_ms?new Date(model.updated_ms).toLocaleDateString('pl-PL'):'';
    text(ctx,new URL(model.profile_url,location.origin).href,pad,h-pad*.65,Math.round(w*.013),p[3]+'99',w*.6);
    text(ctx,stamp,w*.76,h-pad*.65,Math.round(w*.012),p[3]+'77',w*.18);
    return {atlas:!!atlas,front:!!front,horse:!!horseTexture,...result};
  }
  function profileAddress(model,card=false){
    const url=new URL(model.profile_url,location.origin);
    url.searchParams.delete('presentation');
    url.pathname=url.pathname.replace(/\/card\/?$/,'')+(card?'/card':'');
    return url;
  }
  async function bootProfile(node){
    if(node.dataset.profileReady===VERSION)return;
    if(node.dataset.profileReady){replaceControls(node,'[data-card-export],[data-card-export-close],[data-profile-copy],[data-profile-view]');node.querySelectorAll('.player-public-model').forEach(canvas=>canvas.remove());}
    node.dataset.profileReady=VERSION;
    let model;try{model=JSON.parse(node.dataset.playerProfileShowcase);}catch{return;}
    const dialog=node.querySelector('dialog'),open=node.querySelector('[data-card-export]');
    const graphic=node.querySelector('[data-player-showcase]'),classic=node.querySelector('[data-profile-classic]'),cardHost=node.querySelector('[data-profile-card]');
    const tabs=[...node.querySelectorAll('[data-profile-view]')];
    function setView(value,history=false){
      if(!classic||!cardHost)return;
      const card=value==='card';node.dataset.profileMode=card?'card':'classic';classic.hidden=card;cardHost.hidden=!card;
      tabs.forEach(tab=>{const selected=tab.dataset.profileView===node.dataset.profileMode;tab.setAttribute('aria-selected',String(selected));tab.tabIndex=selected?0:-1;});
      if(card){cardHost.append(graphic);graphic.activateShowcase?.();}
      else if(!dialog.open)dialog.append(graphic);
      const shareUrl=profileAddress(model,card);const facebook=graphic.querySelector('[data-showcase-social=facebook]'),whatsapp=graphic.querySelector('[data-showcase-social=whatsapp]');if(facebook)facebook.href='https://www.facebook.com/sharer/sharer.php?'+new URLSearchParams({u:shareUrl.href});if(whatsapp)whatsapp.href='https://wa.me/?'+new URLSearchParams({text:'Karta gracza '+model.name+' '+shareUrl.href});
      if(history){const url=profileAddress(model,card);window.history.replaceState(window.history.state,'',url);}
    }
    tabs.forEach((tab,index)=>{tab.addEventListener('click',()=>setView(tab.dataset.profileView,true));tab.addEventListener('keydown',e=>{if(!['ArrowLeft','ArrowRight','Home','End'].includes(e.key))return;e.preventDefault();const next=e.key==='Home'?0:e.key==='End'?tabs.length-1:(index+(e.key==='ArrowLeft'?-1:1)+tabs.length)%tabs.length;tabs[next].focus();setView(tabs[next].dataset.profileView,true);});});
    setView(new URL(location.href).searchParams.get('presentation')==='card'||/^\/players\/.+\/card\/?$/.test(location.pathname)?'card':'classic');
    open.addEventListener('click',()=>{dialog.append(graphic);dialog.showModal();graphic.activateShowcase?.();});
    dialog.querySelector('[data-card-export-close]').addEventListener('click',()=>dialog.close());
    dialog.addEventListener('close',()=>{if(node.dataset.profileMode==='card')cardHost.append(graphic);open.focus();});
    node.querySelector('[data-profile-copy]').addEventListener('click',async()=>{
      const field=node.querySelector('[data-profile-url]'),status=node.querySelector('[data-profile-status]');const url=profileAddress(model,node.dataset.profileMode==='card');field.value=url.href;
      try{await navigator.clipboard.writeText(field.value);status.textContent='Link skopiowany.';}catch{field.hidden=false;field.focus();field.select();status.textContent='Skopiuj zaznaczony link.';}
    });
    const hero=node.querySelector('.profile-hero');if(hero){if(model.design.backdrop==='custom'&&/^\/(?!\/)[a-zA-Z0-9_/?=&%.:-]+$/.test(model.background_url||''))hero.style.setProperty('--player-card-art',`url("${model.background_url}")`);hero.style.setProperty('--card-blur',((model.design.blur??45)*.45)+'px');hero.style.setProperty('--card-x',(model.design.crop_x??50)+'%');hero.style.setProperty('--card-y',(model.design.crop_y??50)+'%');}
    const source=await assets(model),portrait=node.querySelector('.profile-portrait'),image=portrait?.querySelector('img');
    if(portrait&&source.atlas){
      const canvas=document.createElement('canvas');canvas.width=600;canvas.height=700;canvas.className='player-public-model';canvas.setAttribute('role','img');canvas.setAttribute('aria-label','Skin gracza '+model.name);
      const actual=scene(canvas.getContext('2d'),source,model.design.pose,{x:0,y:0,w:canvas.width,h:canvas.height});
      if(actual.renderer==='skinview3d'){if(image)image.hidden=true;portrait.querySelector('.profile-skin-empty')?.remove();portrait.append(canvas);}
    }
    const horseCanvas=node.querySelector('[data-public-horse]');
    if(horseCanvas){const actual=scene(horseCanvas.getContext('2d'),source,'horse',{x:0,y:0,w:horseCanvas.width,h:horseCanvas.height});horseCanvas.hidden=actual.renderer==='unavailable';}
  }
  function boot(){document.querySelectorAll('[data-player-showcase]').forEach(node=>{
    if(node.dataset.showcaseReady===VERSION)return;
    if(node.dataset.showcaseReady)replaceControls(node,'canvas,[data-showcase-format],[data-showcase-download],[data-showcase-copy],[data-showcase-status],[data-showcase-notice],[data-showcase-social],[data-showcase-live-copy],[data-showcase-live-open],[data-share-panel]');
    node.dataset.showcaseReady=VERSION;
    let model;try{model=JSON.parse(node.dataset.playerShowcase);}catch{return;}
    const canvas=node.querySelector('canvas'),state=node.querySelector('[data-showcase-status]');let revision=0,ready=false,started=false,pngFile=null;
    let notice=node.querySelector('[data-showcase-notice]');
    if(!notice){notice=document.createElement('p');notice.className='player-showcase-notice';notice.dataset.showcaseNotice='';notice.setAttribute('role','status');node.querySelector('figure').after(notice);}
    const filename=(width=canvas.width,height=canvas.height)=>'karta-'+model.name.replace(/[^a-zA-Z0-9_-]/g,'_')+'-'+width+'x'+height+'.png';
    const imageButtons=[...node.querySelectorAll('[data-share-image]')];
    const publicUrl=()=>{const url=profileAddress(model,node.closest('[data-player-profile-showcase]')?.dataset.profileMode==='card');return url.href;};
    let sharing=false,clipboardBusy=false;
    function feedback(message){state.textContent=message;}
    function canShareFile(){try{return !!pngFile&&typeof navigator.share==='function'&&typeof navigator.canShare==='function'&&navigator.canShare({files:[pngFile]});}catch{return false;}}
    function canCopyImage(){try{return typeof ClipboardItem==='function'&&typeof navigator.clipboard?.write==='function'&&(!ClipboardItem.supports||ClipboardItem.supports('image/png'));}catch{return false;}}
    function updateSharing(){
      const native=canShareFile();
      imageButtons.forEach(button=>{
        const channel=button.dataset.showcaseSocial;
        button.hidden=channel==='more'?typeof navigator.share!=='function':channel==='discord'?(!native&&!canCopyImage()):!native;
        button.disabled=!ready||sharing||clipboardBusy;
        if(channel==='discord')button.title=native?'Udostępnij PNG przez urządzenie':'Skopiuj obraz do schowka';
      });
    }
    function saveImage(){
      if(!ready||!pngFile)return false;
      const url=URL.createObjectURL(pngFile),a=document.createElement('a');a.href=url;a.download=pngFile.name;document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1500);return true;
    }
    async function copyLink(){
      const field=node.querySelector('[data-showcase-url]');field.value=publicUrl();
      try{await navigator.clipboard.writeText(field.value);feedback('Link skopiowany.');}catch{field.hidden=false;field.focus();field.select();feedback('Skopiuj zaznaczony link.');}
    }
    function liveImageUrl(){
      const url=new URL(model.live_image_url,location.origin);
      url.pathname=url.pathname.replace(/\/card\.png$/,'/card/'+model.design.format+'.png');
      return url.href;
    }
    function updateLiveImage(){const link=node.querySelector('[data-showcase-live-open]');if(link&&model.live_image_url)link.href=liveImageUrl();}
    node.querySelector('[data-showcase-live-copy]')?.addEventListener('click',async()=>{
      const field=node.querySelector('[data-showcase-live-url]');field.value=liveImageUrl();
      try{await navigator.clipboard.writeText(field.value);feedback('Link do aktualnego PNG skopiowany.');}catch{field.hidden=false;field.focus();field.select();feedback('Skopiuj zaznaczony adres obrazu.');}
    });
    function copyImage(){
      if(!ready||!pngFile||clipboardBusy||sharing||!canCopyImage())return;
      try{
        clipboardBusy=true;updateSharing();
        navigator.clipboard.write([new ClipboardItem({'image/png':pngFile})]).then(()=>feedback('Obraz skopiowany.')).catch(()=>feedback('Nie udało się skopiować obrazu.')).finally(()=>{clipboardBusy=false;updateSharing();});
      }catch{clipboardBusy=false;updateSharing();feedback('Nie udało się skopiować obrazu.');}
    }
    function shareImage(channel){
      if(!ready||!pngFile||sharing||clipboardBusy)return;
      const file=pngFile;
      if(channel==='discord'&&!canShareFile()){copyImage();return;}
      function failed(error){feedback(error?.name==='AbortError'?'':'Udostępnianie niedostępne w tej przeglądarce.');}
      try{
        let promise;
        if(canShareFile()){
          // The PNG is cached before this click: no async preparation before the system sheet.
          sharing=true;updateSharing();promise=navigator.share({files:[file]});
        }else if(channel==='more'&&typeof navigator.share==='function'){
          sharing=true;updateSharing();promise=navigator.share({title:'Karta gracza '+model.name,url:publicUrl()});
        }else return;
        Promise.resolve(promise).then(()=>feedback('')).catch(failed).finally(()=>{sharing=false;updateSharing();});
      }catch(error){sharing=false;updateSharing();failed(error);}
    }
    node.querySelectorAll('[data-showcase-social]').forEach(button=>{
      const channel=button.dataset.showcaseSocial;
      if(channel==='facebook'){button.addEventListener('click',()=>button.href='https://www.facebook.com/sharer/sharer.php?'+new URLSearchParams({u:publicUrl()}));button.href='https://www.facebook.com/sharer/sharer.php?'+new URLSearchParams({u:publicUrl()});}
      else if(channel==='whatsapp'){button.addEventListener('click',()=>button.href='https://wa.me/?'+new URLSearchParams({text:'Karta gracza '+model.name+' '+publicUrl()}));button.href='https://wa.me/?'+new URLSearchParams({text:'Karta gracza '+model.name+' '+publicUrl()});}
      else button.addEventListener('click',()=>shareImage(channel));
    });
    // Clean obsolete UI too when the runtime is replaced in an existing HTMX session.
    node.querySelectorAll('[data-share-panel],[data-showcase-app],.player-showcase-social>p').forEach(element=>element.remove());
    node.querySelectorAll('[data-showcase-social]').forEach(button=>{button.removeAttribute('aria-expanded');button.removeAttribute('aria-controls');});
    updateSharing();updateLiveImage();
    const render=async m=>{
      model=m;updateLiveImage();started=true;node.dataset.format=FORMATS[m.design.format]?m.design.format:'landscape';const mine=++revision;ready=false;pngFile=null;
      node.querySelector('[data-showcase-download]').disabled=true;imageButtons.forEach(button=>button.disabled=true);
      state.textContent='Przygotowywanie karty…';updateSharing();
      try{
        await document.fonts.ready;const temp=document.createElement('canvas'),result=await paint(temp,model);
        if(mine!==revision)return;
        const blob=await new Promise(resolve=>temp.toBlob(resolve,'image/png'));
        if(mine!==revision)return;
        if(!blob)throw Error('PNG unavailable');
        canvas.width=temp.width;canvas.height=temp.height;canvas.getContext('2d').drawImage(temp,0,0);canvas.setAttribute('aria-label','Karta gracza '+model.name);
        pngFile=new File([blob],filename(temp.width,temp.height),{type:'image/png'});ready=true;
        node.querySelector('[data-showcase-download]').disabled=false;imageButtons.forEach(button=>button.disabled=false);state.textContent='';updateSharing();
        notice.textContent=result.renderer==='skinview3d'?'':result.front?(result.atlas?'Podgląd 3D nie jest dostępny w tej przeglądarce. Pokazujemy oryginalny obraz skina.':'Z serwera otrzymaliśmy tylko obraz frontu. Pełny model 3D pojawi się po synchronizacji tekstury skina.'):'Skin nie jest jeszcze dostępny. Pojawi się po synchronizacji z serwerem.';
        if(model.design.pose==='riding'&&!result.horse)notice.textContent+=' Wybrany koń nie ma dostępnego obrazu; pokazujemy postać solo.';notice.hidden=!notice.textContent;
      }catch{if(mine===revision){feedback('Nie udało się przygotować karty. Spróbuj ponownie.');updateSharing();}}
    };
    node.renderShowcase=render;
    node.querySelector('[data-showcase-format]').addEventListener('change',e=>{if(FORMATS[e.target.value])render({...model,design:{...model.design,format:e.target.value}});});
    node.querySelector('[data-showcase-download]').addEventListener('click',saveImage);
    node.querySelector('[data-showcase-copy]').addEventListener('click',copyLink);
    node.activateShowcase=()=>{if(!started)render(model);};
    if(!node.closest('dialog'))render(model);
  });document.querySelectorAll('[data-player-profile-showcase]').forEach(bootProfile);}
  window.PlayerShowcase={boot,paint,drawPortrait:async(canvas,model,pose)=>scene(canvas.getContext('2d'),await assets(model),pose,{x:8,y:4,w:canvas.width-16,h:canvas.height-8}),formats:FORMATS,version:VERSION};
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
  document.addEventListener('htmx:afterSwap',boot);
})();
