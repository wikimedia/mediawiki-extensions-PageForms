/*jshint node:true */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );

	grunt.initConfig( {
		jshint: {
			options: {
				jshintrc: true
			},
			all: [
				'**/*.js',
				'!node_modules/**',
				'!vendor/**',
				'!libs/jquery.browser.js',
				'!libs/jquery.dynatree.js',
				'!libs/FancyBox/jquery.fancybox.1.3.4.js',
				'!libs/FancyBox/jquery.fancybox.3.2.10.js',
				'!libs/jsgrid.js',
				'!libs/select2.js',
				'!libs/PF_maps.js'
			]
		},
		banana: {
			all: 'i18n/'
		},
		jsonlint: {
			all: [
				'**/*.json',
				'!node_modules/**',
				'!vendor/**'
			]
		}
	} );

	grunt.registerTask( 'test', [ 'jshint', 'jsonlint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
