import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    capture({ target }) {
        target.dataset.liveValueParam = target.value;
    }
}
