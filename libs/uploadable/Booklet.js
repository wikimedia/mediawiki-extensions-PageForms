/**
 * @class Booklet
 * @extends mw.Upload.Dialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
function Booklet ( config ) {
	Booklet.super.call( this, config );
}

OO.inheritClass( Booklet, mw.Upload.BookletLayout );

/** @inheritDoc */
Booklet.prototype.renderInfoForm = function () {
	this.infoForm = Booklet.super.prototype.renderInfoForm.call( this );
	this.descriptionWidget.setRequired( false );
	this.descriptionWidget.setValidation( () => true );
	return this.infoForm;
};

/**
 * Set the default filename.
 *
 * @param {string} filename
 */
Booklet.prototype.setDefaultFilename = function ( filename ) {
	this.defaultFilename = filename;
};

/** @inheritDoc */
Booklet.prototype.getFile = function () {
	let file = Booklet.super.prototype.getFile.call( this );
	if ( this.defaultFilename ) {
		// Create a new File object with the new name.
		file = new File( [ file ], this.defaultFilename, { type: file.type } );
	}
	return file;
};

module.exports = Booklet;
