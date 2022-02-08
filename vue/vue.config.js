 const InjectPlugin = require('webpack-inject-plugin').default;
 const dev_config = require('./dev_config');


 module.exports = {
  publicPath: "/vue/",
  devServer: {
    disableHostCheck: true,
    public: "http://moodle.local.isae.fr/vue/"
  },
  configureWebpack: config => {
    if (process.env.NODE_ENV === 'production') {
      // mutate config for production..a.
    } else {
      config.plugins.push(
            new InjectPlugin(function() {
                return "NAAS =" + JSON.stringify(dev_config);
            }));
        
    }
  },
  chainWebpack: config => {
    if (process.env.NODE_ENV === 'production') {
      // mutate config for production..a.
    } else {
      config
      .plugin('html')
      .tap(args => {
        args[0]["nugget_id"] = dev_config.nugget_id;
        return args
      });
    }
  }
}
