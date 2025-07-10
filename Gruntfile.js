/* eslint-env node */
module.exports = function (grunt) {
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );

	grunt.initConfig( {
		eslint: {
			options: {
				cache: true,
				fix: grunt.option( 'fix' )
			},
			all: [
				'**/*.{js,json}',
				'!node_modules/**',
				'!vendor/**',
				'!libs/foreign/**'
			]
		},
		banana: {
			all: 'i18n/'
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
