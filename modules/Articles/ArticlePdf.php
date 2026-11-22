<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\Articles;

use SyntaxDevTeam\Cms\Core\ContentRenderer;

/** Local PDF renderer. Only sanitised content and bounded local raster images reach Dompdf. */
final class ArticlePdf
{
    private const REVISION='1';
    public function __construct(private readonly string $cacheDirectory,private readonly string $publicUrl='') {}

    public function html(Article $article): string
    {
        $escape=static fn(string $value): string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');
        if(strlen($article->content)>1048576)throw new \RuntimeException('Artykuł przekracza limit eksportu PDF.');
        $content=(new ContentRenderer())->render($article->content,$article->contentFormat);
        $dom=new \DOMDocument('1.0','UTF-8');
        $previous=libxml_use_internal_errors(true);
        try{$dom->loadHTML('<?xml encoding="UTF-8"><div id="pdf-content">'.$content.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
        foreach(iterator_to_array($dom->getElementsByTagName('*')) as $element){
            $href=$element->getAttribute('href');$src=$element->getAttribute('src');$alt=$element->getAttribute('alt');
            foreach(iterator_to_array($element->attributes) as $attribute)$element->removeAttribute($attribute->name);
            if($element->tagName==='a'){
                if(str_starts_with($href,'/') && !str_starts_with($href,'//'))$href=$this->publicUrl.$href;
                if(preg_match('~\A(?:https?://|mailto:)~i',$href))$element->setAttribute('href',$href);
            }
            if($element->tagName==='img'){
                $data=$this->image($src);
                if($data!==null){$element->setAttribute('src',$data);$element->setAttribute('alt',$alt);}
                else $element->parentNode?->replaceChild($dom->createTextNode($alt!==''?'[Ilustracja: '.$alt.']':''),$element);
            }
        }
        // The content wrapper ID was deliberately removed with all author attributes.
        $body=$dom->getElementsByTagName('body')->item(0);$safe='';
        foreach($body?->childNodes??[] as $node)$safe.=$dom->saveHTML($node);
        $url=$this->publicUrl.'/article/'.rawurlencode($article->slug);
        $date=$article->publishedAt??$article->updatedAt;
        $css=file_get_contents(dirname(__DIR__,2).'/templates/shared/assets/css/document-pdf.css');
        ob_start();
        require dirname(__DIR__,2).'/templates/Shared/views/document-pdf.php';
        return (string)ob_get_clean();
    }

    public function render(Article $article): string
    {
        if(!is_dir($this->cacheDirectory) && !mkdir($this->cacheDirectory,0770,true) && !is_dir($this->cacheDirectory))throw new \RuntimeException('Nie można przygotować PDF.');
        $html=$this->html($article);
        $key=hash('sha256',self::REVISION.$html);$path=$this->cacheDirectory.'/'.$article->id.'-'.$key.'.pdf';
        $lock=fopen($this->cacheDirectory.'/render.lock','c');if(!$lock)throw new \RuntimeException('Nie można zablokować renderera PDF.');
        try{
            // A single bounded render per installation; cached downloads do not wait for another article.
            if(is_file($path))return (string)file_get_contents($path);
            if(!flock($lock,LOCK_EX|LOCK_NB))throw new \RuntimeException('Generator PDF jest zajęty. Spróbuj ponownie za chwilę.');
            if(is_file($path))return (string)file_get_contents($path);
            require_once __DIR__.'/pdf/autoload.php';
            $options=new \Dompdf\Options();
            $options->setIsRemoteEnabled(false);$options->setIsPhpEnabled(false);$options->setIsJavascriptEnabled(false);
            $options->setChroot([__DIR__.'/pdf/lib/dompdf/lib/fonts']);
            $options->setAllowedProtocols(['file://','data://']);
            $options->setTempDir($this->cacheDirectory);$options->setFontCache($this->cacheDirectory);
            $options->setDefaultFont('DejaVu Sans');$options->setIsFontSubsettingEnabled(true);
            $pdf=new \Dompdf\Dompdf($options);$pdf->setPaper('A4');$pdf->loadHtml($html,'UTF-8');$pdf->render();
            $pdf->getCanvas()->page_text(500,810,'{PAGE_NUM} / {PAGE_COUNT}',null,8,[.4,.4,.4]);
            $bytes=$pdf->output();
            $temporary=tempnam($this->cacheDirectory,'.pdf-');if(!$temporary)throw new \RuntimeException('Nie można zapisać PDF.');
            try{if(file_put_contents($temporary,$bytes)!==strlen($bytes) || !chmod($temporary,0660) || !rename($temporary,$path))throw new \RuntimeException('Nie można zapisać PDF.');}finally{if(is_file($temporary))unlink($temporary);}
            foreach(glob($this->cacheDirectory.'/'.$article->id.'-*.pdf')?:[] as $old)if($old!==$path)unlink($old);
            return $bytes;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }

    private function image(string $source): ?string
    {
        if($this->publicUrl!=='' && str_starts_with($source,$this->publicUrl.'/'))$source=substr($source,strlen($this->publicUrl));
        if(!preg_match('~\A/(?:uploads/media|templates)/[a-zA-Z0-9_./-]+\.(?:png|jpe?g|gif|webp)\z~D',$source))return null;
        $root=dirname(__DIR__,2);$path=realpath($root.$source);
        if(!$path || (!str_starts_with($path,$root.'/uploads/media/') && !str_starts_with($path,$root.'/templates/')) || !is_file($path) || filesize($path)>2*1024*1024)return null;
        $info=@getimagesize($path);
        if(!$info || $info[0]*$info[1]>16000000 || !in_array($info['mime']??'',['image/png','image/jpeg','image/gif','image/webp'],true))return null;
        return 'data:'.$info['mime'].';base64,'.base64_encode((string)file_get_contents($path));
    }
}
