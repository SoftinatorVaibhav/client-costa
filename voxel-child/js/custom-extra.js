jQuery(document).ready(function($) {
  $(document).on('click', '.chbs-button-style-2', function() {
    // Give it a moment for the section to load, then scroll
    setTimeout(function() {
      $('html, body').animate({
        scrollTop: $(document).height()
      }, 800); // 800ms = smooth scroll
    }, 500); // wait for content to render
  });
});
