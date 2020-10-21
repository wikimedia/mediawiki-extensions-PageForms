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
				'!libs/FullCalendar-2.9.1/fullcalendar.js',
				'!libs/FullCalendar-3.9.0/fullcalendar.js',
				'!libs/FullCalendar-3.9.0/locale-all.js',
				'!libs/FancyBox/jquery.fancybox.1.3.4.js',
				'!libs/FancyBox/jquery.fancybox.3.2.10.js',
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
