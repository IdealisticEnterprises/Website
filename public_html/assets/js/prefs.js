/* Loaded in <head> without defer: applies the visitor's saved preferences before the first paint. */
(function () {
  'use strict';

  // Dock position chosen with the Dock's own button: "bottom" (default) or "top" (inside the island)
  try {
    if (localStorage.getItem('dock-position') === 'top') {
      document.documentElement.setAttribute('data-dock', 'top');
    }
  } catch (error) {
    // Storage unavailable (private mode or blocked): the Dock stays at the bottom
  }
})();
