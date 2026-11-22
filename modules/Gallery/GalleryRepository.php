<?php
declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\Gallery;
use PDO;
use SyntaxDevTeam\Cms\Database\CrudApp;
final class GalleryRepository
{
    public function __construct(private readonly CrudApp $db) {}
    public function categories(): array { return $this->db->query('SELECT * FROM gallery_categories ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC); }
    public function category(int $id): bool { return count(array_filter($this->categories(), static fn(array $c): bool => (int)$c['id'] === $id)) === 1; }
    public function statusCounts(): array
    {
        $counts = ['pending' => 0, 'published' => 0, 'rejected' => 0, 'all' => 0];
        foreach ($this->db->query('SELECT status, COUNT(*) AS total FROM gallery_photos GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (array_key_exists($row['status'], $counts)) $counts[$row['status']] = (int) $row['total'];
            $counts['all'] += (int) $row['total'];
        }
        return $counts;
    }

    public function categoryCounts(): array
    {
        $counts = [];
        foreach ($this->db->query('SELECT category_id, COUNT(*) AS total FROM gallery_photos GROUP BY category_id')->fetchAll(PDO::FETCH_ASSOC) as $row) $counts[(int) $row['category_id']] = (int) $row['total'];
        return $counts;
    }

    public function find(int $id): ?array {
        $row=$this->db->query('SELECT * FROM gallery_photos WHERE id=:id',[':id'=>$id])->fetch(PDO::FETCH_ASSOC);
        return is_array($row)?$row:null;
    }
    public function listing(string $scope, int $owner, int $category, int $page, string $status='pending'): array {
        $where=$scope==='mine'?'p.user_id=:owner':($scope==='moderate'?"p.status='pending'":"p.status='published'");
        if($scope==='moderate') $where=$status==='all'?'1=1':"p.status=:status";
        $params=$scope==='mine'?[':owner'=>$owner]:[];
        if($scope==='moderate'&&$status!=='all')$params[':status']=in_array($status,['pending','published','rejected'],true)?$status:'pending';
        if($category>0){$where.=' AND p.category_id=:category';$params[':category']=$category;}
        $total=(int)$this->db->query('SELECT COUNT(*) FROM gallery_photos p WHERE '.$where,$params)->fetchColumn();
        $page=max(1,min($page,max(1,(int)ceil($total/24))));
        $rows=$this->db->query('SELECT p.*, c.name AS category_name FROM gallery_photos p JOIN gallery_categories c ON c.id=p.category_id WHERE '.$where.' ORDER BY p.id DESC LIMIT 24 OFFSET '.(($page-1)*24),$params)->fetchAll(PDO::FETCH_ASSOC);
        return ['photos'=>$rows,'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/24))];
    }
    /** Public-only cover, independent of pagination and private account views. */
    public function coverPhotos(int $category = 0): array
    {
        $params = [];
        $where = "status = 'published'";
        if ($category > 0) { $where .= ' AND category_id = :category'; $params[':category'] = $category; }
        return $this->db->query('SELECT id FROM gallery_photos WHERE ' . $where . ' ORDER BY id DESC LIMIT 6', $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function submit(int $owner,array $data,string $stored,bool $publish=false): int {
        $pdo=$this->db->connection()->pdo;
        $pdo->beginTransaction();
        try {
            // Serialize per-account quota checks on MySQL; tests also support SQLite.
            if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') $this->db->query('SELECT id FROM users WHERE id=:id FOR UPDATE',[':id'=>$owner]);
            $count=(int)$this->db->query("SELECT COUNT(*) FROM gallery_photos WHERE user_id=:id AND status='pending'",[':id'=>$owner])->fetchColumn();
            if(!$publish&&$count>=20) throw new \InvalidArgumentException('Masz już 20 zdjęć oczekujących. Poczekaj na ocenę administracji.');
            $id=(int)$this->db->create('gallery_photos',array_replace($data,['user_id'=>$owner,'stored_name'=>$stored,'status'=>$publish?'published':'pending','moderated_by'=>$publish?$owner:null,'moderated_at'=>$publish?gmdate('Y-m-d H:i:s'):null]));
            $pdo->commit();return $id;
        } catch(\Throwable $e){$pdo->rollBack();throw $e;}
    }
    /** @param list<int> $ids */
    public function byIds(array $ids): array
    {
        if ($ids === []) return [];
        $params = []; $holders = [];
        foreach ($ids as $i => $id) { $key = ':photo' . $i; $params[$key] = $id; $holders[] = $key; }
        return $this->db->query('SELECT * FROM gallery_photos WHERE id IN (' . implode(',', $holders) . ') ORDER BY id DESC', $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Atomic metadata-only edit. Publication decisions are preserved. */
    public function describe(array $rows, int $actor): void
    {
        $pdo = $this->db->connection()->pdo;
        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $statement = $this->db->update('gallery_photos', $row['data'] + ['revision' => $row['revision'] + 1], ['id' => $row['id'], 'revision' => $row['revision']]);
                if ($statement?->rowCount() !== 1) throw new \InvalidArgumentException('Jedno ze zdjęć zostało już zmienione. Odśwież partię i sprawdź aktualne dane.');
            }
            $pdo->commit();
        } catch (\Throwable $error) { $pdo->rollBack(); throw $error; }
    }

    public function moderate(int $id,int $revision,int $actor,array $data): void {
        $s=$this->db->update('gallery_photos',$data+['moderated_by'=>$actor,'moderated_at'=>gmdate('Y-m-d H:i:s'),'revision'=>$revision+1],['id'=>$id,'revision'=>$revision]);
        if($s?->rowCount()!==1) throw new \InvalidArgumentException('Zdjęcie zostało już zmienione. Odśwież stronę i sprawdź aktualny stan.');
    }
    public function withdraw(int $id,int $owner): void { $this->db->delete('gallery_photos',['id'=>$id,'user_id'=>$owner]); }
    public function delete(int $id,int $revision): void {
        if($this->db->delete('gallery_photos',['id'=>$id,'revision'=>$revision])?->rowCount()!==1)throw new \InvalidArgumentException('Zdjęcie zostało już zmienione. Odśwież stronę.');
    }
    public function saveCategory(string $name,int $id): void {
        if($id>0){if(!$this->category($id))throw new \InvalidArgumentException('Nie znaleziono kategorii.');$this->db->update('gallery_categories',['name'=>$name],['id'=>$id]);}
        else $this->db->create('gallery_categories',['name'=>$name]);
    }
    public function deleteCategory(int $id): void {
        $count=(int)$this->db->query('SELECT COUNT(*) FROM gallery_photos WHERE category_id=:id',[':id'=>$id])->fetchColumn();
        if($count)throw new \InvalidArgumentException('Kategoria zawiera zdjęcia. Najpierw przenieś je do innej kategorii.');
        $this->db->delete('gallery_categories',['id'=>$id]);
    }
}
