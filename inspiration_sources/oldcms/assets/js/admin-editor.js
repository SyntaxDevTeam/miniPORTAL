(() => {
    const textarea = document.querySelector('textarea[data-wysiwyg="quill"]');
    const editor = document.querySelector('#cms-content-editor');
    const form = textarea ? textarea.closest('form') : null;

    if (!textarea || !editor || !form || typeof Quill === 'undefined') {
        return;
    }

    textarea.classList.add('wysiwyg-source');
    textarea.required = false;
    editor.innerHTML = textarea.value || '';

    const quill = new Quill(editor, {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote', 'code-block'],
                ['link', 'image'],
                ['clean'],
            ],
        },
    });

    const syncContent = () => {
        const text = quill.getText().trim();
        textarea.value = text === '' ? '' : quill.root.innerHTML.trim();
    };

    quill.on('text-change', syncContent);
    form.addEventListener('submit', syncContent);
    syncContent();
})();
