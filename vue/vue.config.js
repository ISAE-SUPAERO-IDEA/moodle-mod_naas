const InjectPlugin = require("webpack-inject-plugin").default;
let dev_config;
const path = require("path");

if (process.env.NODE_ENV != "production") {
  dev_config = require("./dev_config");
}

module.exports = {
  publicPath: "/vue/",
  outputDir: path.resolve(__dirname, "../assets/vue"),
  devServer: {
    disableHostCheck: true,
    public: "http://moodle.local.isae.fr/vue/",
  },
  configureWebpack: (config) => {
    if (process.env.NODE_ENV === "production") {
      // Configure output file
      config.optimization.splitChunks = false;
      config.output.filename = "naas_widget.js";
    } else {
      // Inject developpement configuration
      config.plugins.push(
        new InjectPlugin(function () {
          return "NAAS =" + JSON.stringify(dev_config);
        })
      );
    }
  },
  chainWebpack: (config) => {
    if (process.env.NODE_ENV === "production") {
      // Do not output html file
      config.plugin("html").tap((args) => {
        args[0].template = "public/blank.html";
        args[0].inject = false;
        args[0].cache = false;
        args[0].minify = false;
        args[0].filename = "blank.html";
        return args;
      });
    } else {
      // Inject initial nugget_id for developpement
      config.plugin("html").tap((args) => {
        args[0]["nugget_id"] = dev_config.nugget_id;
        return args;
      });
    }
  },
};
