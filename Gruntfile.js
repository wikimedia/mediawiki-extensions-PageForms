/* eslint-env node */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );

	grunt.initConfig( {
		eslint: {
			options: {
				cache: true
			},
			all: [
				'**/*.{js,json}',
				'!node_modules/**',
				'!vendor/**',
				'!libs/jquery.browser.js',
				'!libs/jquery.rateyo.js',
				'!libs/FullCalendar/fullcalendar.js',
				'!libs/FullCalendar/locale-all.js',
				'!libs/FancyBox/jquery.fancybox.js',
				'!libs/jstree.js',
				'!libs/jsuites.js',
				'!libs/jexcel.js',
				'!libs/select2.js',
				'!libs/Sortable.js'
			]
		},
		banana: {
			all: 'i18n/'
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
