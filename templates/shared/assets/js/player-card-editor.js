(() => {
  'use strict';
  const boot=()=>document.querySelectorAll('[data-card-studio]').forEach(studio=>{
    if(studio.dataset.editorReady)return;studio.dataset.editorReady='1';studio.classList.add('is-enhanced');
    const form=studio.querySelector('[data-card-form]'),node=studio.querySelector('[data-player-showcase]'),choices=studio.querySelector('[data-card-stat-choices]');
    if(!form||!node)return;
    const original=JSON.parse(node.dataset.playerShowcase),stats=JSON.parse(studio.dataset.cardStatistics),horses=JSON.parse(studio.dataset.cardHorses);
    const field=name=>form.elements.namedItem(name),saveState=studio.querySelector('[data-card-save-state]');
    let previewBackground=null,renderTimer,uploadRevision=0,submitting=false,dirty=false,baseline='',baselineFile='',uploadPending=false,portraitKey='';
    const undoButton=studio.querySelector('[data-studio-undo]'),redoButton=studio.querySelector('[data-studio-redo]'),fileInput=field('background');
    function snapshot(){return {values:[...form.elements].filter(e=>e.name&&!['file','submit'].includes(e.type)).map(e=>[e,e.value,e.checked]),order:[...choices.children].map(e=>e.dataset.cardStat),file:fileInput?.files[0]||null,preview:previewBackground};}
    const fileKey=file=>file?[file.name,file.size,file.lastModified,file.type].join('|'):'';
    function signature(state){return JSON.stringify([state.values.map(([e,v,c])=>[e.name,v,c]),state.order]);}
    let history=[snapshot()],historyIndex=0;
    function record(){const next=snapshot(),last=history[historyIndex];if(signature(last)!==signature(next)||fileKey(last.file)!==fileKey(next.file)||last.preview!==next.preview){history=history.slice(0,historyIndex+1);history.push(next);if(history.length>30)history.shift();historyIndex=history.length-1;}if(undoButton)undoButton.disabled=historyIndex===0;if(redoButton)redoButton.disabled=historyIndex===history.length-1;}
    function restore(index){if(index<0||index>=history.length)return;uploadRevision++;uploadPending=false;form.querySelectorAll('[type=submit]').forEach(b=>b.disabled=false);historyIndex=index;const saved=history[index];saved.values.forEach(([e,v,c])=>{e.value=v;e.checked=c;});saved.order.forEach(id=>{const row=[...choices.children].find(e=>e.dataset.cardStat===id);if(row)choices.append(row);});previewBackground=saved.preview;studio.querySelector('[data-background-state]').textContent=saved.file?.name||'';if(fileInput){const transfer=new DataTransfer();if(saved.file)transfer.items.add(saved.file);fileInput.files=transfer.files;}update(true,false);record();}
    undoButton?.addEventListener('click',()=>restore(historyIndex-1));redoButton?.addEventListener('click',()=>restore(historyIndex+1));
    const tabs=[...studio.querySelectorAll('[data-studio-tab]')];
    function section(name){tabs.forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.studioTab===name)));studio.querySelectorAll('[data-studio-section]').forEach(panel=>panel.hidden=panel.dataset.studioSection!==name);}
    const backdrops=studio.querySelector('[data-studio-backdrops]');if(backdrops)studio.querySelector('.studio-background').prepend(backdrops);
    tabs.forEach(button=>button.addEventListener('click',()=>section(button.dataset.studioTab)));section('design');
    studio.querySelector('[data-background-reset]')?.addEventListener('click',()=>{for(const [name,value] of Object.entries({blur:45,shade:35,crop_x:50,crop_y:50,zoom:100}))field(name).value=value;update();});
    studio.querySelector('[data-background-remove]')?.addEventListener('click',()=>{uploadRevision++;previewBackground=null;fileInput.value='';field('background_id').value='';if(field('backdrop').value==='custom')field('backdrop').value='world';studio.querySelector('[data-background-state]').textContent='';update();});
    const dropzone=studio.querySelector('.studio-upload');
    dropzone?.addEventListener('dragover',event=>{event.preventDefault();dropzone.classList.add('is-dragging');});
    dropzone?.addEventListener('dragleave',()=>dropzone.classList.remove('is-dragging'));
    dropzone?.addEventListener('drop',event=>{event.preventDefault();dropzone.classList.remove('is-dragging');if(event.dataTransfer.files.length!==1){studio.querySelector('[data-background-state]').textContent='Wybierz jeden obraz.';return;}fileInput.files=event.dataTransfer.files;fileInput.dispatchEvent(new Event('change',{bubbles:true}));});
    fileInput?.addEventListener('change',async()=>{
      const mine=++uploadRevision,file=fileInput.files[0],status=studio.querySelector('[data-background-state]');if(!file)return;uploadPending=true;status.textContent='Przetwarzanie obrazu…';form.querySelectorAll('[type=submit]').forEach(b=>b.disabled=true);
      try{
        if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>8*1024*1024)throw Error('Wybierz JPG, PNG lub WebP do 8 MB.');
        const data=await new Promise((resolve,reject)=>{const reader=new FileReader();reader.onload=()=>resolve(reader.result);reader.onerror=()=>reject(Error('Nie można odczytać pliku.'));reader.readAsDataURL(file);});
        const image=await new Promise((resolve,reject)=>{const img=new Image();img.onload=()=>resolve(img);img.onerror=()=>reject(Error('Nie można odczytać obrazu.'));img.src=data;});
        if(image.width<320||image.height<180||image.width*image.height>20000000)throw Error('Obraz musi mieć co najmniej 320 × 180 px i maksymalnie 20 megapikseli.');
        if(mine!==uploadRevision)return;
        const scale=Math.min(1,2560/Math.max(image.width,image.height)),canvas=document.createElement('canvas');canvas.width=Math.round(image.width*scale);canvas.height=Math.round(image.height*scale);const context=canvas.getContext('2d');context.fillStyle='#120d23';context.fillRect(0,0,canvas.width,canvas.height);context.drawImage(image,0,0,canvas.width,canvas.height);
        previewBackground=canvas.toDataURL('image/webp',.85);field('backdrop').value='custom';status.textContent=file.name+' · '+image.width+' × '+image.height+' px';update();
      }catch(error){if(mine!==uploadRevision)return;const transfer=new DataTransfer();if(history[historyIndex].file)transfer.items.add(history[historyIndex].file);fileInput.files=transfer.files;status.textContent=error.message;}finally{if(mine===uploadRevision){uploadPending=false;form.querySelectorAll('[type=submit]').forEach(b=>b.disabled=false);}}
    });
    window.addEventListener('beforeunload',event=>{if(dirty&&!submitting&&studio.isConnected){event.preventDefault();event.returnValue='';}});
    studio.addEventListener('keydown',event=>{if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='s'){event.preventDefault();form.requestSubmit(form.querySelector('[value="save"]'));}else if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='z'&&!event.target.matches('textarea,input[type=text],input[type=search],input:not([type])')){event.preventDefault();restore(historyIndex+(event.shiftKey?1:-1));}});
    function update(changed=true,remember=true){
      const selected=[...choices.querySelectorAll('input:checked')].map(e=>e.value);
      const horse=horses[field('horse_id').value]||null;
      const riding=form.querySelector('[name="pose"][value="riding"]'),canRide=!!horse?.texture_url&&!!original.texture_url;
      riding.disabled=!canRide;riding.closest('label').classList.toggle('is-unavailable',!canRide);
      const help=studio.querySelector('[data-card-pose-help]');
      const unavailable=!original.texture_url?'Jazda 3D wymaga pełnej tekstury skina. Oczekiwanie na synchronizację podczas gry (PPB 0.14.0 lub nowszy).':horse?'Jazda będzie dostępna po synchronizacji umaszczenia wybranego konia.':'Aby wybrać jazdę, wskaż poniżej własnego konia z potwierdzonym umaszczeniem.';
      if(help){help.textContent=canRide?'':unavailable;help.hidden=canRide;}
      if(field('pose').value==='riding'&&!canRide){field('pose').value='standing';saveState.textContent=unavailable;changed=false;}
      const design={...original.design};
      for(const key of ['blur','shade','crop_x','crop_y','zoom']){design[key]=Number(field(key).value);const out=studio.querySelector('[data-range-output='+key+']');if(out)out.textContent=design[key]+'%';}
      design.background_id=field('background_id').value||null;
      const custom=form.querySelector('[name=backdrop][value=custom]');if(custom){custom.disabled=!previewBackground&&!design.background_id;custom.closest('label').classList.toggle('is-unavailable',custom.disabled);}
      studio.querySelector('[data-background-remove]').hidden=!previewBackground&&!design.background_id;
      const thumbnail=custom?.closest('label').querySelector('.card-choice-visual'),background=previewBackground||(design.background_id?original.background_url:null);
      if(thumbnail){if(background){let image=thumbnail.querySelector('img');if(!image){image=document.createElement('img');image.alt='';thumbnail.append(image);}if(image.getAttribute('src')!==background)image.src=background;}else thumbnail.querySelector('img')?.remove();}['pose','palette','layout','backdrop','format'].forEach(n=>design[n]=field(n).value);
      ['show_time','show_horse_stats'].forEach(n=>design[n]=field(n).checked);design.show_status=false;
      design.horse_id=horse?.id||null;design.bio=field('bio').value.trim();design.statistics=selected;
      let metrics=original.metrics.filter(m=>m.label!=='Czas gry');
      if(design.show_time&&original.play_time)metrics.push(original.play_time);
      const model={...original,background_url:previewBackground||original.background_url,design,bio:design.bio,status:null,horse:horse&&{...horse,statistics:design.show_horse_stats?horse.statistics:[]},metrics,statistics:selected.map(id=>({id,...stats[id]})).filter(s=>s.label)};
      const nextPortraitKey=[model.texture_url,model.horse?.texture_url,model.horse?.marking_url].join('|');
      if(portraitKey!==nextPortraitKey&&window.PlayerShowcase?.drawPortrait){portraitKey=nextPortraitKey;for(const radio of form.querySelectorAll('[name=pose]')){const visual=radio.closest('label').querySelector('.card-choice-visual');let portrait=visual.querySelector('canvas');if(!portrait){portrait=document.createElement('canvas');portrait.width=192;portrait.height=160;portrait.setAttribute('aria-hidden','true');visual.append(portrait);}window.PlayerShowcase.drawPortrait(portrait,model,radio.value).catch(()=>portrait.remove());}}
      clearTimeout(renderTimer);renderTimer=setTimeout(()=>node.renderShowcase?.(model),90);
      studio.querySelector('[data-card-bio-count]').textContent=[...field('bio').value].length+'/280';
      studio.querySelector('[data-card-stat-count]').textContent='Wybrano '+selected.length+' z 3';
      choices.querySelectorAll('[data-card-stat]').forEach(row=>{
        const input=row.querySelector('input'),index=selected.indexOf(input.value);
        input.disabled=!input.checked&&selected.length>=3;
        row.querySelector('[data-card-move="up"]').disabled=!input.checked||index===0;
        row.querySelector('[data-card-move="down"]').disabled=!input.checked||index===selected.length-1;
      });
      if(remember&&changed)record();
      if(changed){dirty=signature(snapshot())!==baseline||fileKey(snapshot().file)!==baselineFile;saveState.textContent=dirty?'Niezapisane zmiany':'Szkic bez zmian';}
    }
    form.addEventListener('input',e=>{if(!e.target.matches('[data-card-stat-search],[data-background-file]'))update();});
    studio.querySelector('[data-card-stat-search]').addEventListener('input',e=>{
      const term=e.target.value.toLocaleLowerCase('pl');choices.querySelectorAll('[data-card-stat]').forEach(row=>row.hidden=!row.querySelector('input').checked&&!row.textContent.toLocaleLowerCase('pl').includes(term));
    });
    choices.addEventListener('click',e=>{
      const b=e.target.closest('[data-card-move]');if(!b)return;
      const row=b.closest('[data-card-stat]'),rows=[...choices.children].filter(r=>r.querySelector('input').checked),i=rows.indexOf(row),up=b.dataset.cardMove==='up',other=rows[i+(up?-1:1)];
      if(!other)return;choices.insertBefore(row,up?other:other.nextSibling);update();row.querySelector('input').focus();
    });
    node.querySelector('[data-showcase-format]').addEventListener('change',e=>{field('format').value=e.target.value;update();});
    form.addEventListener('submit',event=>{if(uploadPending){event.preventDefault();return;}submitting=true;dirty=false;});
    update(false);history=[snapshot()];historyIndex=0;baseline=signature(history[0]);baselineFile=fileKey(history[0].file);
  });
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
  document.addEventListener('htmx:afterSwap',boot);
})();
