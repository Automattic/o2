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
					'modules/**/*.js'
                ]
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
                            '**.php',
                            '!node_modules/**'
                        ]
                    }
                }
            }
        };

    grunt.initConfig( cfg );

    grunt.loadNpmTasks('grunt-phplint');
    grunt.loadNpmTasks('grunt-contrib-jshint');
    grunt.loadNpmTasks('grunt-wp-i18n');

    grunt.registerTask('default', [
        'phplint',
        'jshint'
    ]);
};
