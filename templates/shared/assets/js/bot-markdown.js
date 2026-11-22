/* Discord-style preview: builds DOM nodes only; never interprets HTML or loads images. */
(() => {
  const text=(parent,value)=>parent.append(document.createTextNode(value));
  function inline(parent,source,depth=0){
    if(depth>8){text(parent,source);return;}
    const tokens=/\\([\\`*_~>|\[\]()])|`([^`\n]+)`|\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)|\*\*\*([\s\S]+?)\*\*\*|\*\*([\s\S]+?)\*\*|__([\s\S]+?)__|~~([\s\S]+?)~~|\*([^*\n]+)\*|_([^_\n]+)_/g;
    let at=0,match;
    while((match=tokens.exec(source))){text(parent,source.slice(at,match.index));at=tokens.lastIndex;
      if(match[1]){text(parent,match[1]);continue;}
      if(match[2]){const el=document.createElement('code');el.textContent=match[2];parent.append(el);continue;}
      if(match[3]){const link=document.createElement('a');try{const url=new URL(match[4]);if(!['http:','https:'].includes(url.protocol)||url.username||url.password)throw Error();link.href=url.href;}catch{ text(parent,match[0]);continue;}link.target='_blank';link.rel='noopener noreferrer';inline(link,match[3],depth+1);parent.append(link);continue;}
      const index=[5,6,7,8,9,10].find(i=>match[i]!==undefined),el=document.createElement(({5:'strong',6:'strong',7:'u',8:'s',9:'em',10:'em'})[index]);
      if(index===5){const em=document.createElement('em');inline(em,match[index],depth+1);el.append(em);}else inline(el,match[index],depth+1);parent.append(el);
    }text(parent,source.slice(at));
  }
  function lines(parent,source,mode){
    let list=null;
    source.split('\n').forEach(line=>{
      const item=line.match(/^\s*(?:([-*]) |(\d+)\. )(.*)$/);
      if(item){const tag=item[2]?'ol':'ul';if(!list||list.tagName.toLowerCase()!==tag){list=document.createElement(tag);if(item[2])list.start=Number(item[2]);parent.append(list);}const li=document.createElement('li');inline(li,item[3]);list.append(li);return;}list=null;
      const quote=line.match(/^> ?(.*)$/),heading=mode!=='field'&&line.match(/^(#{1,3}) (.*)$/),el=document.createElement(quote?'blockquote':'div');
      if(heading)el.className='bot-md-heading bot-md-h'+heading[1].length;
      inline(el,quote?quote[1]:heading?heading[2]:line);if(!line)el.append(document.createElement('br'));parent.append(el);
    });
  }
  function render(source,mode='description'){
    const fragment=document.createDocumentFragment(),parts=String(source).slice(0,12000).split(/```(?:[a-zA-Z0-9_+-]+\n)?([\s\S]*?)```/g);
    parts.forEach((part,i)=>{if(i%2){const pre=document.createElement('pre'),code=document.createElement('code');code.textContent=part.replace(/^\n|\n$/g,'');pre.append(code);fragment.append(pre);}else lines(fragment,part,mode);});return fragment;
  }
  function format(target,command){
    const start=target.selectionStart,end=target.selectionEnd,selected=target.value.slice(start,end),pairs={bold:['**','**'],italic:['*','*'],underline:['__','__'],strike:['~~','~~'],code:['`','`'],block:['```\n','\n```'],spoiler:['||','||']};
    let from=start,to=end,replacement,selectionStart,selectionEnd;
    if(['quote','list','ordered','heading'].includes(command)){
      from=start===0?0:target.value.lastIndexOf('\n',start-1)+1;to=target.value.indexOf('\n',Math.max(start,end-1));if(to===-1)to=target.value.length;
      replacement=target.value.slice(from,to).split('\n').map((line,i)=>({quote:'> ',list:'- ',ordered:(i+1)+'. ',heading:'## '})[command]+(line||'Tekst')).join('\n');selectionStart=from;selectionEnd=from+replacement.length;
    }else if(command==='link'){const label=selected||'tekst linku';replacement='['+label+'](https://example.com)';selectionStart=from+label.length+3;selectionEnd=from+replacement.length-1;
    }else if(pairs[command]){const [before,after]=pairs[command],body=selected||'tekst';replacement=before+body+after;selectionStart=from+before.length;selectionEnd=selectionStart+body.length;}else return;
    const status=document.querySelector('[data-bot-md-status="'+target.id+'"]');
    if(target.maxLength>=0&&target.value.length-(to-from)+replacement.length>target.maxLength){if(status)status.textContent='Brak miejsca — skróć tekst przed dodaniem formatowania.';return;}
    target.setRangeText(replacement,from,to,'select');target.focus();target.setSelectionRange(selectionStart,selectionEnd);if(status)status.textContent='';target.dispatchEvent(new Event('input',{bubbles:true}));
  }
  window.MiniportalDiscordMarkdown={render,format};
})();
