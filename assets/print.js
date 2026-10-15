document.addEventListener('DOMContentLoaded', function () {
    var printButtons = document.querySelectorAll('[data-print-page]');
    for (var i = 0; i < printButtons.length; i++) {
        printButtons[i].addEventListener('click', function () {
            window.print();
        });
    }
});
