/**
 * Keep upstream jexcel.js free of local edits.
 *
 * ResourceLoader runs every file in this module inside one function and
 * passes require() in. jexcel.js does `var jSuites` and then calls
 * require('jsuites') when that binding is empty. The var is hoisted over
 * this whole module, so it hides the global that jsuites.js just set.
 * Copy that global onto the hoisted binding and the upstream require is skipped.
 */
// eslint-disable-next-line no-implicit-globals
jSuites = window.jSuites;
