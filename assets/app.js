/*
 * Page behaviour loaded from a file because the Content-Security-Policy
 * (script-src 'self') blocks inline handlers such as onclick/onsubmit.
 */
document.addEventListener('DOMContentLoaded', function () {
    var printButtons = document.querySelectorAll('[data-print-page]');
    for (var i = 0; i < printButtons.length; i++) {
        printButtons[i].addEventListener('click', function () {
            window.print();
        });
    }

    var confirmForms = document.querySelectorAll('form[data-confirm]');
    for (var j = 0; j < confirmForms.length; j++) {
        confirmForms[j].addEventListener('submit', function (event) {
            var message = this.getAttribute('data-confirm');
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    }
});
