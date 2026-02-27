document.addEventListener('DOMContentLoaded', () => {
    const forms = Array.from(document.querySelectorAll('#insertBeritaForm, #editBeritaForm'));
    if (forms.length === 0) {
        return;
    }

    const sanitizeInitialHtml = (html) => {
        const trimmed = String(html || '').trim();
        if (trimmed === '') {
            return {
                html: '',
                fontSize: 'normal'
            };
        }

        const parser = new DOMParser();
        const doc = parser.parseFromString(`<div>${trimmed}</div>`, 'text/html');
        const root = doc.body.firstElementChild;

        if (!root) {
            return {
                html: '',
                fontSize: 'normal'
            };
        }

        let targetNode = root;
        let fontSize = 'normal';
        const wrapper = root.firstElementChild;
        const wrapperClass = wrapper && wrapper.className ? String(wrapper.className) : '';
        const matched = wrapperClass.match(/news-font-(small|normal|large)/);
        if (wrapper && root.childElementCount === 1 && matched && wrapper.tagName === 'DIV') {
            targetNode = wrapper;
            fontSize = matched[1] || 'normal';
        }

        const hasBlockTags = targetNode.querySelector('p, ul, ol, li, h1, h2, h3, h4, blockquote, br, strong, em, b, i');
        if (hasBlockTags) {
            return {
                html: targetNode.innerHTML,
                fontSize
            };
        }

        return {
            html: trimmed
                .split(/\n{2,}/)
                .map((part) => `<p>${part.replace(/\n/g, '<br>')}</p>`)
                .join(''),
            fontSize
        };
    };

    const normalizeForStorage = (editor, fontSize = 'normal') => {
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

        const normalized = clone.innerHTML
            .replace(/<p>\s*<\/p>/g, '')
            .replace(/<p>(\s|&nbsp;)*<br\s*\/?>\s*<\/p>/g, '')
            .trim();

        if (normalized === '') {
            return '';
        }

        if (fontSize === 'small' || fontSize === 'large') {
            return `<div class="news-font-${fontSize}">${normalized}</div>`;
        }

        return normalized;
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
        const fontSizeControl = form.querySelector('[data-editor-font-size]');
        const toolbarButtons = Array.from(form.querySelectorAll('.news-rich-btn[data-editor-command]'));

        if (!source || !editor) {
            return;
        }

        const initial = sanitizeInitialHtml(source.value);
        editor.innerHTML = initial.html;

        const applyEditorFontSize = (value) => {
            const safe = value === 'small' || value === 'large' ? value : 'normal';
            editor.classList.remove('news-font-small', 'news-font-normal', 'news-font-large');
            editor.classList.add(`news-font-${safe}`);
            editor.setAttribute('data-font-size', safe);
            if (fontSizeControl && fontSizeControl.value !== safe) {
                fontSizeControl.value = safe;
            }
            return safe;
        };

        applyEditorFontSize(initial.fontSize || 'normal');

        if (editor.textContent.trim() === '') {
            editor.innerHTML = '<p><br></p>';
        }

        if (fontSizeControl) {
            fontSizeControl.addEventListener('change', () => {
                applyEditorFontSize(fontSizeControl.value);
                source.value = normalizeForStorage(editor, editor.getAttribute('data-font-size') || 'normal');
            });
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
            source.value = normalizeForStorage(editor, editor.getAttribute('data-font-size') || 'normal');
        });

        form.addEventListener('submit', (event) => {
            const normalized = normalizeForStorage(editor, editor.getAttribute('data-font-size') || 'normal');
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
