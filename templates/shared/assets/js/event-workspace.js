(() => {
    const initWizard = () => {
        const root = document.querySelector('.event-wizard');
        if (!root || root.dataset.ready) return;
        root.dataset.ready = '1';
        const form = root.querySelector('form');
        const input = name => form.elements.namedItem(name);
        const field = name => input(name)?.closest('.mb-3');
        const steps = [
            ['Cel wpisu', ['entry_kind']],
            ['Szczegóły', ['title','owner_id','priority','starts_at','ends_at','assignee_id','due_at','task_status','plan','public_title','summary','description','location']],
            ['Widoczność', ['publication_mode','publication_timing','publication_days','publication_date','discord_mode']],
            ['Sprawdź i utwórz', []]
        ];
        const nav = document.createElement('nav'); nav.className = 'event-wizard-steps'; nav.setAttribute('aria-label','Kroki kreatora');
        const status = document.createElement('p'); status.className = 'event-wizard-status'; status.setAttribute('aria-live','polite');
        form.before(nav, status);
        const panels = steps.map(([label,names],i) => {
            const section = document.createElement('fieldset'); section.className = 'event-wizard-panel';
            const legend = document.createElement('legend'); legend.textContent = `${i+1}. ${label}`; section.append(legend);
            names.forEach(name => { const f = field(name); if (f && input(name).type !== 'hidden') section.append(f); });
            form.prepend(section);
            const button = document.createElement('button'); button.type = 'button';button.textContent = `${i+1}. ${label}`;
            button.addEventListener('click',()=>go(i)); nav.append(button);
            return section;
        });
        panels.forEach(p=>form.append(p));
        // Native, keyboard and touch accessible help, shared across themes.
        form.querySelectorAll('.form-text').forEach(help=>{
            const details=document.createElement('details');details.className='event-wizard-help';
            const summary=document.createElement('summary');summary.textContent='?'; summary.setAttribute('aria-label','Wskazówka: '+(help.parentElement.querySelector('label')?.textContent.replace('?', '').trim()||'pole'));
            help.before(details);details.append(summary,help);
            details.parentElement.querySelector('.badge[title]')?.remove();
        });
        const choices = document.createElement('div'); choices.className='event-wizard-choices';
        const descriptions={event:['Wydarzenie dla graczy','Turniej, konkurs, spotkanie. Ty decydujesz, kiedy pokażesz zapowiedź.'],task:['Zadanie administracji','Konkretna praca z osobą odpowiedzialną, terminem i postępem. Zawsze wewnętrzna.'],plan:['Plan na przyszłość','Pomysł lub scenariusz do omówienia z zespołem. Termin jest opcjonalny. Zawsze wewnętrzny.']};
        Object.entries(descriptions).forEach(([kind,[title,description]])=>{
            const button=document.createElement('button');button.type='button';button.dataset.kind=kind;
            const strong=document.createElement('strong');strong.textContent=title;const p=document.createElement('span');p.textContent=description;button.append(strong,p);
            button.addEventListener('click',()=>{input('entry_kind').value=kind;input('entry_kind').dispatchEvent(new Event('change',{bubbles:true}));});choices.append(button);
        });
        field('entry_kind').after(choices);
        const summary=document.createElement('div');summary.className='event-wizard-summary';panels[3].append(summary);
        const actions=document.createElement('div');actions.className='event-wizard-actions';
        const back=document.createElement('button');back.type='button';back.textContent='← Wstecz';
        const next=document.createElement('button');next.type='button';next.textContent='Dalej →';
        const submit=form.querySelector('[type="submit"]');actions.append(back,next,submit);form.append(actions);
        let current=0;
        const setVisible=(name,show)=>{const f=field(name);if(f)f.hidden=!show;if(input(name))input(name).disabled=!show;};
        const update=()=>{
            const kind=input('entry_kind').value,isEvent=kind==='event',isTask=kind==='task';
            choices.querySelectorAll('button').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.kind===kind)));
            ['starts_at','ends_at'].forEach(n=>setVisible(n,!isTask));
            ['assignee_id','due_at','task_status'].forEach(n=>setVisible(n,isTask));
            ['public_title','summary','description','location','publication_mode'].forEach(n=>setVisible(n,isEvent));
            if(!isEvent)input('publication_mode').value='private';
            const mode=input('publication_mode').value,scheduled=isEvent&&mode==='scheduled',date=input('publication_timing')?.value==='date';
            setVisible('publication_timing',scheduled);setVisible('publication_days',scheduled&&!date);setVisible('publication_date',scheduled&&date);setVisible('discord_mode',isEvent&&mode!=='private');
            ['public_title','description','starts_at','ends_at'].forEach(n=>input(n).required=isEvent&&mode!=='private');
            input('publication_days')?.setAttribute('min','1');input('publication_days')?.setAttribute('max','365');
            if(input('publication_days'))input('publication_days').required=scheduled&&!date;
            if(input('publication_date'))input('publication_date').required=scheduled&&date;
            const start=input('starts_at').value,end=input('ends_at').value;
            input('ends_at').setCustomValidity(!isTask && ((!!start)!=(!!end) || (start&&end&&end<=start))?'Podaj obie daty. Koniec musi być późniejszy niż początek.':'');
            let planned=input('publication_date')?.value.replace('T',' ')||'';
            if(scheduled&&!date&&start){const day=new Date(start.slice(0,10)+'T12:00:00Z');day.setUTCDate(day.getUTCDate()-Number(input('publication_days').value));if(!Number.isNaN(day.getTime()))planned=day.toISOString().slice(0,10)+' '+start.slice(11);}
            const now=new Intl.DateTimeFormat('sv-SE',{timeZone:'Europe/Warsaw',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).format(new Date());
            const timingError=scheduled&&planned&&(planned<=now || planned>=start.replace('T',' '))?'Publikacja musi nastąpić w przyszłości i przed rozpoczęciem wydarzenia. Zmień termin lub wybierz publikację od razu.':'';
            if(input('publication_days'))input('publication_days').setCustomValidity(scheduled&&!date?timingError:'');
            if(input('publication_date'))input('publication_date').setCustomValidity(scheduled&&date?timingError:'');
            const visibility=!isEvent||mode==='private'?'Tylko administracja. Gracze nie zobaczą tego wpisu.':mode==='now'?'Publicznie, od razu po utworzeniu.':`Automatyczna publikacja: ${planned||'uzupełnij termin'}${date?'':' ('+input('publication_days').value+' dni przed startem)'}. Europe/Warsaw.`;
            status.textContent=`Krok ${current+1} z 4 · ${descriptions[kind][0]} · ${visibility}`;
            summary.replaceChildren();
            const heading=document.createElement('h3');heading.textContent=input('title').value||'Uzupełnij nazwę wpisu';summary.append(heading);
            const rows=[['Rodzaj',descriptions[kind][0]],['Widoczność',visibility],['Termin',isTask?(input('due_at').value.replace('T',' ')||'Bez terminu'):[input('starts_at').value.replace('T',' '),input('ends_at').value.replace('T',' ')].filter(Boolean).join(' — ')||'Do ustalenia'],['Odpowiedzialność',(isTask?input('assignee_id'):input('owner_id')).selectedOptions[0]?.textContent],['Opis wewnętrzny',input('plan').value||'Nie podano']];
            if(isEvent&&mode!=='private')rows.push(['Tytuł dla graczy',input('public_title').value],['Opis dla graczy',input('description').value]);
            rows.forEach(([label,value])=>{const p=document.createElement('p');const b=document.createElement('strong');b.textContent=label+': ';p.append(b,document.createTextNode(value||'Nie podano'));summary.append(p);});
            submit.textContent=isEvent&&mode==='now'?'Utwórz i opublikuj wydarzenie':isEvent&&mode==='scheduled'?'Utwórz i zaplanuj publikację':`Utwórz ${isTask?'zadanie':kind==='plan'?'plan':'wydarzenie'} dla administracji`;
        };
        const validStep=i=>{const invalid=[...panels[i].querySelectorAll('input,select,textarea')].find(el=>!el.disabled&&!el.checkValidity());if(!invalid)return true;show(i);invalid.reportValidity();return false;};
        function go(i){update();if(i>current){for(let s=current;s<i;s++){if(!validStep(s)){show(s);return;}}}show(i);}
        function show(i){current=i;panels.forEach((p,n)=>p.hidden=n!==i);[...nav.children].forEach((b,n)=>{if(n===i)b.setAttribute('aria-current','step');else b.removeAttribute('aria-current');});back.hidden=i===0;next.hidden=i===3;submit.hidden=i!==3;update();const legend=panels[i].querySelector('legend');legend.tabIndex=-1;legend.focus();}
        back.addEventListener('click',()=>go(current-1));next.addEventListener('click',()=>go(current+1));
        form.addEventListener('input',update);form.addEventListener('change',update);
        form.noValidate=true;
        form.addEventListener('submit',event=>{update();for(let i=0;i<3;i++){const invalid=[...panels[i].querySelectorAll('input,select,textarea')].find(el=>!el.disabled&&!el.checkValidity());if(invalid){event.preventDefault();show(i);invalid.reportValidity();return;}}if(current!==3){event.preventDefault();go(3);}});
        show(0);
    };
    const init = () => {
        if (!document.querySelector('.event-workspace')) return;
        const active = document.querySelector('.event-workspace [aria-current="page"]');
        if (active) {
            const nav = active.closest('nav');
            nav.scrollLeft = active.offsetLeft - nav.offsetLeft - (nav.clientWidth - active.clientWidth) / 2;
        }
        document.querySelectorAll('[data-richtext]').forEach(editor => {
            editor.querySelector('[data-richtext-mode="html"]')?.click();
            const modes = { html: 'Wizualny', markdown: 'Markdown', source: 'Źródło' };
            const labelModes = () => editor.querySelectorAll('[data-richtext-mode]').forEach(button => {
                button.textContent = modes[button.dataset.richtextMode];
                button.title = button.dataset.richtextMode === 'source' ? 'Edytuj źródło dokumentu' : button.textContent;
            });
            labelModes();
            editor.querySelectorAll('[data-richtext-mode]').forEach(button => button.addEventListener('click', labelModes));
            const toolbar = editor.querySelector('[data-richtext-toolbar]');
            const blocks = [...editor.querySelectorAll('[data-richtext-block]')];
            if (toolbar && blocks.length) {
                const select = document.createElement('select');
                select.className = 'event-editor-block';
                select.setAttribute('aria-label', 'Styl akapitu');
                const labels = { p: 'Akapit', h2: 'Nagłówek', h3: 'Podtytuł', blockquote: 'Cytat' };
                blocks.forEach(button => {
                    const option = document.createElement('option');
                    option.value = button.dataset.richtextBlock;
                    option.textContent = labels[option.value] || button.textContent;
                    select.append(option);
                    button.hidden = true;
                });
                select.addEventListener('change', () => blocks.find(button => button.dataset.richtextBlock === select.value)?.click());
                toolbar.prepend(select);
            }
            const icons = {
                insertUnorderedList: '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
                insertOrderedList: '<path d="M9 6h11M9 12h11M9 18h11M3 4h1v5M2 9h4M2 14c0-3 4-3 4-1 0 1-4 3-4 5h4"/>'
            };
            editor.querySelectorAll('[data-richtext-command]').forEach(button => {
                button.title = button.getAttribute('aria-label') || button.textContent;
                const icon = icons[button.dataset.richtextCommand];
                if (icon) button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' + icon + '</svg>';
            });
        });
        for (const name of ['resources', 'risks', 'rules', 'rewards']) {
            const input = document.querySelector('[name="' + name + '"]');
            const field = input?.closest('.mb-3');
            if (!field) continue;
            const details = document.createElement('details');
            details.className = 'event-editor-fold';
            const summary = document.createElement('summary');
            summary.textContent = (field.querySelector('.form-label')?.textContent || name) + (input.value.trim() ? ' · uzupełnione' : ' · opcjonalnie');
            field.before(details);
            details.append(summary, field);
        }
        initWizard();
        const forms = [...document.querySelectorAll('form[action="/admin/events"]')];
        let dirty = false;
        forms.forEach(form => {
            form.addEventListener('input', () => { dirty = true; });
            form.addEventListener('change', () => { dirty = true; });
            form.addEventListener('submit', () => { dirty = false; });
        });
        window.addEventListener('beforeunload', event => {
            if (!dirty) return;
            event.preventDefault();
            event.returnValue = '';
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
