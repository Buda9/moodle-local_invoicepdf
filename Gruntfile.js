module.exports = function(grunt) {
  // Project configuration
  grunt.initConfig({
    // Uglify task for minifying JavaScript files and generating map files
    uglify: {
      options: {
        sourceMap: true, // Generates a source map
        mangle: false // Optionally, you can disable variable name mangling
      },
      build: {
        files: [{
          expand: true,
          cwd: 'amd/src/', // Source folder
          src: ['*.js', '!*.min.js'], // Include all .js files except already minified
          dest: 'amd/build/', // Destination folder
          ext: '.min.js', // Add .min.js extension
          extDot: 'last' // Only replace the last dot in file name
        }]
      }
    }
  });

  // Load the plugins
  grunt.loadNpmTasks('grunt-contrib-uglify');

  // Define the amd task
  grunt.registerTask('amd', ['uglify']);

  // Default task(s)
  grunt.registerTask('default', ['uglify']);
};
