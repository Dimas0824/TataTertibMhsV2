document.addEventListener('DOMContentLoaded', () => {
    const forms = Array.from(document.querySelectorAll('#insertBeritaForm, #editBeritaForm'));
    if (forms.length === 0) {
        return;
    }

    const sanitizeInitialHtml = (html) => {
        const trimmed = String(html || '').trim();
        if (trimmed === '') {
            return '';
        }

        const parser = new DOMParser();
        const doc = parser.parseFromString(`<div>${trimmed}</div>`, 'text/html');
        const root = doc.body.firstElementChild;

        if (!root) {
            return '';
        }

        const hasBlockTags = root.querySelector('p, ul, ol, li, h1, h2, h3, h4, blockquote, br, strong, em, b, i');
        if (hasBlockTags) {
            return root.innerHTML;
        }

        return trimmed
            .split(/\n{2,}/)
            .map((part) => `<p>${part.replace(/\n/g, '<br>')}</p>`)
            .join('');
    };

    const normalizeForStorage = (editor) => {
        const clone = editor.cloneNode(true);

        clone.querySelectorAll('script, style').forEach((node) => node.remove());

        clone.querySelectorAll('*').forEach((el) => {
            Array.from(el.attributes).forEach((attr) => {
                const attrName = attr.name.toLowerCase();
                if (attrName.startsWith('on') || attrName === 'style') {
                    el.removeAttribute(attr.name);
                }
            });
        });

        return clone.innerHTML
            .replace(/<p>\s*<\/p>/g, '')
            .replace(/<p>(\s|&nbsp;)*<br\s*\/?>\s*<\/p>/g, '')
            .trim();
    };

    const plainTextLength = (html) => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(`<div>${html}</div>`, 'text/html');
        const value = (doc.body.textContent || '').replace(/\s+/g, ' ').trim();
        return value.length;
    };

    const runCommand = (command, value = null) => {
        document.execCommand(command, false, value);
    };

    forms.forEach((form) => {
        const source = form.querySelector('.news-rich-source');
        const editor = form.querySelector('[data-rich-editor]');
        const toolbarButtons = Array.from(form.querySelectorAll('.news-rich-btn[data-editor-command]'));

        if (!source || !editor) {
            return;
        }

        editor.innerHTML = sanitizeInitialHtml(source.value);

        if (editor.textContent.trim() === '') {
            editor.innerHTML = '<p><br></p>';
        }

        toolbarButtons.forEach((button) => {
            button.addEventListener('click', () => {
                editor.focus();
                const command = button.getAttribute('data-editor-command') || '';
                const value = button.getAttribute('data-editor-value');

                if (!command) {
                    return;
                }

                if (command === 'removeFormat') {
                    runCommand('removeFormat');
                    runCommand('formatBlock', 'P');
                } else if (value) {
                    runCommand(command, value);
                } else {
                    runCommand(command);
                }
            });
        });

        editor.addEventListener('keydown', (event) => {
            if (event.key === 'Tab') {
                event.preventDefault();
                runCommand('insertHTML', '&emsp;');
            }
        });

        editor.addEventListener('input', () => {
            source.value = normalizeForStorage(editor);
        });

        form.addEventListener('submit', (event) => {
            const normalized = normalizeForStorage(editor);
            if (plainTextLength(normalized) === 0) {
                event.preventDefault();
                editor.focus();
                window.alert('Konten berita tidak boleh kosong.');
                return;
            }

            source.value = normalized;
        });
    });
});
