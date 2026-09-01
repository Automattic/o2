/* global module */
module.exports = function(grunt) {
	var childProcess = require('child_process');
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
					'outputStyle': 'expanded',
					implementation: require('sass'),
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
					cmd: 'vendor/bin/phpunit',
					args: ['-c', 'phpunit.xml.dist']
				}
			},
			qunit: {
				all: [ 'tests/qunit/index.html' ]
			}
		};

	grunt.initConfig( cfg );

	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-contrib-qunit');
	grunt.loadNpmTasks('grunt-sass');
	grunt.loadNpmTasks('grunt-wp-i18n');
	grunt.loadNpmTasks('grunt-rtlcss');

	grunt.registerTask( 'phplint', 'Runs PHP syntax checks.', function() {
		var failures = [];
		var files = grunt.file.expand( { filter: 'isFile' }, cfg.phplint.files );

		files.forEach( function( file ) {
			var result = childProcess.spawnSync( 'php', [ '-l', file ], {
				encoding: 'utf8'
			} );

			if ( result.error ) {
				grunt.log.error( result.error.message );
				failures.push( file );
				return;
			}

			if ( result.status !== 0 ) {
				grunt.log.error( result.stdout || result.stderr );
				failures.push( file );
			}
		} );

		if ( failures.length ) {
			grunt.fail.warn( 'PHP syntax check failed for ' + failures.length + ' file(s).' );
		}

		grunt.log.ok( 'No syntax errors detected in ' + files.length + ' PHP files.' );
	} );

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
