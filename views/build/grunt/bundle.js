module.exports = function(grunt) { 

    var requirejs   = grunt.config('requirejs') || {};
    var clean       = grunt.config('clean') || {};
    var copy        = grunt.config('copy') || {};

    var root        = grunt.option('root');
    var libs        = grunt.option('mainlibs');
    var ext         = require(root + '/tao/views/build/tasks/helpers/extensions')(grunt, root);

    /**
     * Remove bundled and bundling files
     */
    clean.taodacsimplebundle = ['output',  root + '/taoDacSimple/views/js/controllers.min.js'];
    
    /**
     * Compile tao files into a bundle 
     */
    requirejs.taodacsimplebundle = {
        options: {
            baseUrl : '../js',
            dir : 'output',
            mainConfigFile : './config/requirejs.build.js',
            paths : { 'taoDacSimple' : root + '/taoDacSimple/views/js' },
            modules : [{
                name: 'taoDacSimple/controller/routes',
                include : ext.getExtensionsControllers(['taoDacSimple']),
                exclude : ['mathJax', 'mediaElement'].concat(libs)
            }]
        }
    };

    /**
     * copy the bundles to the right place
     */
    copy.taodacsimplebundle = {
        files: [
            { src: ['output/taoDacSimple/controller/routes.js'],  dest: root + '/taoDacSimple/views/js/controllers.min.js' },
            { src: ['output/taoDacSimple/controller/routes.js.map'],  dest: root + '/taoDacSimple/views/js/controllers.min.js.map' }
        ]
    };

    grunt.config('clean', clean);
    grunt.config('requirejs', requirejs);
    grunt.config('copy', copy);

    // bundle task
    grunt.registerTask('taodacsimplebundle', ['clean:taodacsimplebundle', 'requirejs:taodacsimplebundle', 'copy:taodacsimplebundle']);
};
