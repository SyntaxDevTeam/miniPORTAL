<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\FileLibrary;

use PDO;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class FileRepository
{
    public function __construct(private readonly CrudApp $db) {}
    public function find(int $id): ?array
    {
        $row=$this->db->query('SELECT * FROM file_library_files WHERE id=:id AND deleted_at IS NULL',[':id'=>$id])->fetch(PDO::FETCH_ASSOC);
        return $row?:null;
    }
    public function page(string $search,int $page): array
    {
        $where="deleted_at IS NULL AND original_name LIKE :q ESCAPE '!'";
        $params=[':q'=>'%'.strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']).'%'];
        $total=(int)$this->db->query('SELECT COUNT(*) FROM file_library_files WHERE '.$where,$params)->fetchColumn();
        $pages=max(1,(int)ceil($total/25));$page=max(1,min($page,$pages));
        $files=$this->db->query('SELECT * FROM file_library_files WHERE '.$where.' ORDER BY id DESC LIMIT 25 OFFSET '.(($page-1)*25),$params)->fetchAll(PDO::FETCH_ASSOC);
        return compact('files','page','pages','total');
    }
    public function attached(int $article): array
    {
        return $this->db->query('SELECT f.* FROM file_library_files f JOIN file_library_article_links l ON l.file_id=f.id WHERE l.article_id=:article AND f.deleted_at IS NULL ORDER BY f.original_name,f.id',[':article'=>$article])->fetchAll(PDO::FETCH_ASSOC);
    }
    public function downloadable(int $file, int $article = 0): bool
    {
        // Optional Articles integration must be active, and the parent currently published.
        if(!$this->db->count('modules_config',['module_id'=>'articles','status'=>'active']))return false;
        $sql = "SELECT 1 FROM file_library_article_links l JOIN articles a ON a.id=l.article_id
            JOIN file_library_files f ON f.id=l.file_id
            WHERE l.file_id=:file AND a.status='published' AND f.deleted_at IS NULL";
        $parameters = [':file' => $file];
        if ($article !== 0) {
            $sql .= ' AND a.id=:article';
            $parameters[':article'] = $article;
        }

        return (bool) $this->db->query($sql . ' LIMIT 1', $parameters)->fetchColumn();
    }
    public function articleExists(int $id): bool
    {
        return $this->db->count('modules_config',['module_id'=>'articles','status'=>'active'])>0 && $this->db->count('articles',['id'=>$id])>0;
    }
    public function create(array $data,int $actor,callable $audit): int
    {
        return $this->transaction(function()use($data,$actor,$audit){
            $id=(int)$this->db->create('file_library_files',$data+['created_by'=>$actor,'created_at'=>gmdate('Y-m-d H:i:s')]);$audit($id);return $id;
        });
    }
    public function link(int $file,int $article,bool $attach,callable $audit): void
    {
        $this->transaction(function()use($file,$article,$attach,$audit){
            if(!$this->find($file) || !$this->articleExists($article)) throw new \InvalidArgumentException('Wybierz istniejący plik i artykuł.');
            $where=['file_id'=>$file,'article_id'=>$article];
            if($attach){if(!$this->db->count('file_library_article_links',$where))$this->db->create('file_library_article_links',$where);}
            else $this->db->delete('file_library_article_links',$where);
            $audit($file);
        });
    }
    public function delete(int $file,callable $audit): ?array
    {
        return $this->transaction(function()use($file,$audit){
            $row=$this->find($file);if(!$row)return null;
            $this->db->update('file_library_files',['deleted_at'=>gmdate('Y-m-d H:i:s')],['id'=>$file]);
            $this->db->delete('file_library_article_links',['file_id'=>$file]);$audit($file);return $row;
        });
    }
    private function transaction(callable $work): mixed
    {
        $pdo=$this->db->connection()->pdo;$pdo->beginTransaction();
        try{$result=$work();$pdo->commit();return $result;}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
