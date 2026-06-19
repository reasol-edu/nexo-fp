import { Controller } from '@hotwired/stimulus';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['editor', 'input'];
    static values  = { placeholder: String };

    connect() {
        this.quill = new Quill(this.editorTarget, {
            theme: 'snow',
            placeholder: this.placeholderValue,
            formats: ['header', 'bold', 'italic', 'underline', 'blockquote', 'list', 'link'],
            modules: {
                toolbar: [
                    [{ header: [2, false] }],
                    ['bold', 'italic', 'underline'],
                    ['blockquote'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link'],
                    ['clean'],
                ],
            },
        });

        const stored = this.inputTarget.value.trim();
        if (stored) {
            this.quill.clipboard.dangerouslyPasteHTML(stored);
        }

        this.quill.on('text-change', () => {
            const html = this.quill.root.innerHTML;
            this.inputTarget.value = html === '<p><br></p>' ? '' : html;
        });
    }

    disconnect() {
        if (this.quill) {
            this.quill = null;
        }
    }
}
