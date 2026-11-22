<?php

declare(strict_types=1);

/** Explicit local library map; no package manager or generated loader is required. */
spl_autoload_register(static function(string $class): void {
    if($class==='Dompdf\\Cpdf'){require_once __DIR__.'/lib/dompdf/lib/Cpdf.php';return;}
    $prefixes=[
        'Dompdf\\'=>'dompdf/src/',
        'FontLib\\'=>'php-font-lib/src/FontLib/',
        'Svg\\'=>'php-svg-lib/src/Svg/',
        'Masterminds\\'=>'html5/src/',
        'Sabberworm\\CSS\\'=>'css-parser/src/',
    ];
    foreach($prefixes as $prefix=>$path){
        if(!str_starts_with($class,$prefix))continue;
        $relative=substr($class,strlen($prefix));
        if(!preg_match('/\A[A-Za-z0-9_\\\\]+\z/D',$relative))return;
        $file=__DIR__.'/lib/'.$path.str_replace('\\','/',$relative).'.php';
        if(is_file($file))require_once $file;
        return;
    }
});
// Compatibility aliases supplied by the CSS parser's upstream distribution.
require_once __DIR__.'/lib/css-parser/src/Rule/Rule.php';
require_once __DIR__.'/lib/css-parser/src/RuleSet/RuleContainer.php';
