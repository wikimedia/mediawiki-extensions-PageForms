/**
 * Select2 installs itself on jQuery only in its browser-global branch.
 * ResourceLoader always passes a CommonJS `module`, and the upstream file
 * then exports a factory instead of calling it. Hide that binding so the
 * unmodified select2.full.js still attaches $.fn.select2.
 */
// `module` here is ResourceLoader's parameter, not a Node global.
// eslint-disable-next-line no-global-assign, no-implicit-globals
module = undefined;
