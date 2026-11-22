const path=require('path'),fs=require('fs'),webpack=require('webpack'),TerserPlugin=require('terser-webpack-plugin');
const destination=path.resolve(__dirname,'../../templates/shared/assets/js');
webpack({mode:'production',context:__dirname,entry:'./entry.js',resolve:{modules:[process.env.PLAYER_CARD_NODE_MODULES||'node_modules','node_modules']},output:{path:destination,filename:'player-model-renderer.js'},
  optimization:{minimizer:[new TerserPlugin({extractComments:false,terserOptions:{format:{comments:/^!|@license/}}})]},
  plugins:[new webpack.BannerPlugin('Player card engine: skinview3d 3.4.2, skinview-utils 0.7.1, Three.js 0.156.1 (MIT). Source: tools/player-card-engine. Licenses: /templates/shared/assets/vendor/player-card-engine-LICENSES.txt')]
},(error,stats)=>{if(error||stats.hasErrors()){console.error(error||stats.toString({all:false,errors:true}));process.exitCode=1;return;}
  fs.writeFileSync(path.resolve(destination,'../vendor/player-card-engine-LICENSES.txt'),['skinview3d','skinview-utils','three'].map(name=>name+'\n'+fs.readFileSync(path.join(path.dirname(require.resolve(name)),'../LICENSE'),'utf8')).join('\n\n'));
  console.log(stats.toString({all:false,assets:true,errors:true,warnings:true}));
});
