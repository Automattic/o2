/* global module */
module.exports = function(grunt) {
	var cfg = {
			pkg: grunt.file.readJSON('package.json'),
			phplint: {
				files: [
					'*.php',
					'**/*.php'
				]
			},
			jshint: {
				options: grunt.file.readJSON('.jshintrc'),
				src: [
					'js/**/*.js',
					'modules/**/*.js',
					// External libraries:
					'!js/utils/caret.js',
					'!js/utils/cocktail.js',
					'!js/utils/enquire.js',
					'!js/utils/jquery.highlight.js',
					'!js/utils/jquery.hotkeys.js',
					'!js/utils/jquery.placeholder.js',
					'!js/utils/moment.js'
				]
			},
			sass: {
				options: {
					'outputStyle': 'expanded'
				},
				dist: {
					files: {
						'css/style.css': 'css/style.scss'
					}
				}
			},
			makepot: {
				o2: {
					options: {
						domainPath: '/languages',
						exclude: [
							'node_modules'
						],
						mainFile:    'o2.php',
						potFilename: 'o2.pot'
					}
				}
			},
			addtextdomain: {
				o2: {
					options: {
						textdomain: 'o2'
					},
					files: {
						src: [
							'*.php',
							'**/*.php',
							'!node_modules/**'
						]
					}
				}
			},
			rtlcss: {
				o2: {
					src: 'css/style.css',
					dest: 'css/style-rtl.css'
				},
				modules: {
					expand: true,
					cwd: 'modules',
					dest: 'modules/',
					ext: '-rtl.css',
					src: ['**/css/style.css']
				}
			},
			phpunit: {
				'default': {
					cmd: 'phpunit',
					args: ['-c', 'phpunit.xml.dist']
				}
			}
		};

	grunt.initConfig( cfg );

	grunt.loadNpmTasks('grunt-phplint');
	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-sass');
	grunt.loadNpmTasks('grunt-wp-i18n');
	grunt.loadNpmTasks('grunt-rtlcss');

	grunt.registerTask('default', [
		'phplint',
		'jshint',
		'sass',
		'rtlcss'
	]);

	grunt.registerMultiTask('phpunit', 'Runs PHPUnit tests.', function() {
		grunt.util.spawn({
			cmd: this.data.cmd,
			args: this.data.args,
			opts: {stdio: 'inherit'}
		}, this.async());
	});

	grunt.registerTask( 'travis:lint', 'Runs code linting Travis CI tasks', [ 'phplint', 'jshint' ] );
	grunt.registerTask( 'travis:phpunit', 'Runs PHPUnit Travis CI tasks.', 'phpunit' );
};
