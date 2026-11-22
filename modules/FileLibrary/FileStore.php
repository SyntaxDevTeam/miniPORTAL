<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\FileLibrary;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/** Opaque, non-executable storage. Archives are inspected, never extracted. */
final class FileStore
{
    public const MAX_BYTES = 20 * 1024 * 1024;
    public const TEXT_EXTENSIONS = ['txt','md','java','py','js','ts','c','cpp','h','hpp','cs','go','rs','kt','swift','sql','json','yaml','yml','xml','html','css','sh','php','rb','toml','ini','properties'];
    public function __construct(private readonly string $directory) {}

    public static function inspect(string $path, string $name): array
    {
        $name=trim($name);
        if ($name==='' || strlen($name)>180 || preg_match('~[\x00-\x1f\x7f/\\\\]~',$name) || basename($name)!==$name || $name[0]==='.') {
            throw new InvalidArgumentException('Podaj prostą nazwę pliku (do 180 bajtów), bez ścieżki i znaków sterujących.');
        }
        $size=filesize($path);
        if ($size===false || $size<1 || $size>self::MAX_BYTES) throw new InvalidArgumentException('Plik musi mieć od 1 bajta do 20 MiB.');
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($ext==='zip') {
            if (!in_array($mime,['application/zip','application/x-zip','application/x-zip-compressed'],true)) throw new InvalidArgumentException('Plik nie jest archiwum ZIP.');
            $zip=new ZipArchive();
            if ($zip->open($path,ZipArchive::RDONLY)!==true) throw new InvalidArgumentException('Nieprawidłowe archiwum ZIP.');
            try {
                if ($zip->numFiles<1 || $zip->numFiles>2000) throw new InvalidArgumentException('ZIP może zawierać od 1 do 2000 wpisów.');
                $total=0;
                for($i=0;$i<$zip->numFiles;$i++) {
                    $entry=$zip->statIndex($i);$entryName=$entry['name']??'';
                    if (!$entry || $entryName==='' || strlen($entryName)>500 || preg_match('~[\x00-\x1f\x7f\\\\:]|^/|(?:^|/)\.\.(?:/|$)~',$entryName) || ($entry['encryption_method']??0)!==0) throw new InvalidArgumentException('ZIP zawiera niedozwoloną ścieżkę lub zaszyfrowane dane.');
                    $opsys=0;$attrs=0;
                    if ($zip->getExternalAttributesIndex($i,$opsys,$attrs) && $opsys===ZipArchive::OPSYS_UNIX && (($attrs>>16)&0170000)===0120000) throw new InvalidArgumentException('ZIP nie może zawierać dowiązań symbolicznych.');
                    $total+=(int)$entry['size'];
                    if($total>100*1024*1024) throw new InvalidArgumentException('Rozpakowany ZIP przekracza 100 MiB.');
                }
            } finally {$zip->close();}
        } elseif ($ext==='pdf') {
            $handle=fopen($path,'rb');$signature=$handle?fread($handle,5):'';if($handle)fclose($handle);
            if($mime!=='application/pdf' || $signature!=='%PDF-') throw new InvalidArgumentException('Plik nie jest dokumentem PDF.');
        } elseif (in_array($ext,self::TEXT_EXTENSIONS,true)) {
            $content=file_get_contents($path);
            if(!is_string($content) || !mb_check_encoding($content,'UTF-8') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$content)) throw new InvalidArgumentException('Kod i tekst muszą być plikiem UTF-8 bez danych binarnych.');
        } else throw new InvalidArgumentException('Dozwolone: ZIP, PDF i tekstowe pliki kodu. Programy wykonywalne spakuj jako przykład źródłowy ZIP.');
        return ['original_name'=>$name,'size_bytes'=>$size,'sha256'=>hash_file('sha256',$path)];
    }

    public function upload(array $file): array
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']??'')) throw new InvalidArgumentException('Nie udało się przesłać pliku. Sprawdź limit uploadu serwera.');
        $metadata=self::inspect($file['tmp_name'],$file['name']);
        if(!is_dir($this->directory) && !mkdir($this->directory,0700,true) && !is_dir($this->directory)) throw new RuntimeException('Magazyn plików jest niedostępny.');
        if(is_link($this->directory)) throw new RuntimeException('Magazyn nie może być dowiązaniem.');
        $key=bin2hex(random_bytes(24)).'.bin';$target=$this->directory.'/'.$key;
        if(!move_uploaded_file($file['tmp_name'],$target)) throw new RuntimeException('Nie można zapisać pliku.');
        if(!chmod($target,0600)){unlink($target);throw new RuntimeException('Nie można zabezpieczyć pliku.');}
        return $metadata+['storage_key'=>$key];
    }

    public function path(string $key): ?string
    {
        if(!preg_match('/\A[a-f0-9]{48}\.bin\z/D',$key) || is_link($this->directory)) return null;
        $path=$this->directory.'/'.$key;
        return is_file($path) && !is_link($path) ? $path : null;
    }
    public function remove(string $key): void
    {
        $path=$this->path($key);
        if($path!==null && !unlink($path)) throw new RuntimeException('Nie można usunąć zawartości pliku.');
    }
}
