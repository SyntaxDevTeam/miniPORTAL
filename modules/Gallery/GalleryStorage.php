<?php
declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\Gallery;
final class GalleryStorage
{
    public function __construct(private readonly string $directory, private readonly ?\Closure $optimizer = null) {}
    public function store(array $file): string {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']??'')) throw new \InvalidArgumentException('Wybierz poprawnie przesłane zdjęcie.');
        $path=$file['tmp_name'];$bytes=filesize($path);
        if(!$bytes || $bytes>8388608)throw new \InvalidArgumentException('Zdjęcie może mieć maksymalnie 8 MiB.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $size=@getimagesize($path);
        if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)||!$size||($size['mime']??'')!==$mime)throw new \InvalidArgumentException('Obsługujemy zdjęcia JPG, PNG i WebP.');
        if($size[0]>12000||$size[1]>12000||$size[0]*$size[1]>24000000)throw new \InvalidArgumentException('Zdjęcie jest zbyt duże. Limit wynosi 24 megapiksele i 12000 px na bok.');
        if(!function_exists('imagewebp'))throw new \RuntimeException('Brak obsługi obrazów GD/WebP.');
        $image=@imagecreatefromstring((string)file_get_contents($path));
        if(!$image)throw new \InvalidArgumentException('Nie udało się odczytać zdjęcia.');
        if(!is_dir($this->directory)&&!mkdir($this->directory,0770,true)&&!is_dir($this->directory))throw new \RuntimeException('Brak magazynu galerii.');
        $name=bin2hex(random_bytes(24)).'.webp';
        try {
            $this->encode($image,min(2560,$size[0]),$this->path($name));
            $this->encode($image,min(720,$size[0]),$this->path($name,true));
        } catch(\Throwable $e){$this->delete($name);throw $e;}
        finally {imagedestroy($image);}
        return $name;
    }
    /**
     * One provider attempt per stored photo, only on an authorized publication path.
     * The source is never handed to the provider in place: failure keeps the local image.
     */
    public function optimizeForPublication(string $name): bool
    {
        if ($this->optimizer === null) return false;
        $path = $this->path($name);
        if (!is_file($path)) throw new \RuntimeException('Nie znaleziono zdjęcia do publikacji.');
        $lock = fopen($path . '.tinify.lock', 'c');
        if ($lock === false) throw new \RuntimeException('Nie można zabezpieczyć publikacji zdjęcia.');
        chmod($path . '.tinify.lock', 0660);
        $temporary = null;
        try {
            if (!flock($lock, LOCK_EX)) throw new \RuntimeException('Nie można zabezpieczyć publikacji zdjęcia.');
            $receipt = $path . '.tinify.json';
            if (is_file($receipt)) return false;
            // Persist before contacting the provider. An ambiguous failure is never auto-retried.
            $this->optimizationReceipt($receipt, 'attempted');
            $temporary = $path . '.opt-' . bin2hex(random_bytes(8)) . '.webp';
            if (!copy($path, $temporary)) throw new \RuntimeException('Nie można przygotować zdjęcia do optymalizacji.');
            chmod($temporary, 0660);
            try {
                if (!(($this->optimizer)($temporary))) {
                    // No API call: configuration absent, optional provider inactive, or quota exhausted.
                    unlink($receipt);
                    return false;
                }
                clearstatcache(true, $temporary);
                $source = @getimagesize($path);
                $result = @getimagesize($temporary);
                if (!$source || !$result || ($result['mime'] ?? '') !== 'image/webp'
                    || $source[0] !== $result[0] || $source[1] !== $result[1]
                    || filesize($temporary) === 0) {
                    throw new \RuntimeException('Niepoprawny wynik optymalizacji zdjęcia.');
                }
                $decoded = @imagecreatefromwebp($temporary);
                if (!$decoded) throw new \RuntimeException('Niepoprawny wynik optymalizacji zdjęcia.');
                imagedestroy($decoded);
                if (filesize($temporary) >= filesize($path)) {
                    $this->optimizationReceipt($receipt, 'unchanged');
                    return false;
                }
                if (!rename($temporary, $path)) throw new \RuntimeException('Nie można zapisać zoptymalizowanego zdjęcia.');
                $temporary = null;
                $this->optimizationReceipt($receipt, 'optimized');
                return true;
            } catch (\Throwable $error) {
                // Provider failure must not remove a valid photo or block approved publication.
                error_log('[Gallery] publication_optimization_failed ' . $error::class);
                $this->optimizationReceipt($receipt, 'failed');
                return false;
            }
        } finally {
            if ($temporary !== null && is_file($temporary)) unlink($temporary);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function optimizationReceipt(string $path, string $status): void
    {
        $json = json_encode(['status' => $status, 'at' => gmdate('c')], JSON_THROW_ON_ERROR);
        if (file_put_contents($path, $json, LOCK_EX) !== strlen($json)) {
            throw new \RuntimeException('Nie można zapisać wyniku optymalizacji.');
        }
        chmod($path, 0660);
    }

    private function encode(\GdImage $image,int $width,string $path): void {
        $height=max(1,(int)round(imagesy($image)*$width/imagesx($image)));
        $out=imagecreatetruecolor($width,$height);
        imagefill($out,0,0,imagecolorallocate($out,18,15,30));
        imagecopyresampled($out,$image,0,0,0,0,$width,$height,imagesx($image),imagesy($image));
        try{if(!imagewebp($out,$path,86))throw new \RuntimeException('Nie udało się zapisać obrazu.');chmod($path,0660);}finally{imagedestroy($out);}
    }
    public function path(string $name,bool $thumb=false): string {
        if(!preg_match('/^[a-f0-9]{48}\.webp$/D',$name))throw new \RuntimeException('Nieprawidłowy obraz.');
        return $this->directory.'/'.($thumb?'thumb-':'').$name;
    }
    public function delete(string $name): void {foreach([false,true] as $thumb){$p=$this->path($name,$thumb);if(is_file($p))unlink($p);}
        foreach(['.tinify.json','.tinify.lock'] as $suffix){$p=$this->path($name).$suffix;if(is_file($p))unlink($p);}}
}
