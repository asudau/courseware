<?php
namespace Mooc\DB;

/**
 * @author  <rlucke@uos.de>
 *
 * @property int $thread_id
 * @property int $post_id
 * @property string $seminar_id
 * @property string $user_id
 * @property \User $user
 * @property string $user_name
 * @property string $content
 * @property tinyint $hidden
 * @property float $mkdate
 * @property float $chdate
 */
class Post extends \SimpleORMap
{
    protected static function configure($config = array())
    {
        $config['db_table'] = 'mooc_posts';

        $config['belongs_to']['user'] = array(
            'class_name'  => 'User',
            'foreign_key' => 'user_id'
        );

        $config['registered_callbacks']['before_store'] = array(
            'denyNobodyPost'
        );

        parent::configure($config);
    }

    public function __construct($id = null)
    {
        parent::__construct($id);
    }

    public function getAllThreadIds($cid)
    {
        $db = \DBManager::get();
        $stmt = $db->prepare("
            SELECT
                thread_id
            FROM
                mooc_posts
            WHERE
                seminar_id = :cid
            GROUP BY
                thread_id
        ");
        $stmt->bindParam(":cid", $cid);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    public function findPosts($thread_id, $cid, $uid, $show_hidden = false)
    {
        $db = \DBManager::get();
        $stmt = $db->prepare("
            SELECT
                *
            FROM
                mooc_posts
            WHERE
                thread_id = :thread_id
            AND
                seminar_id = :cid
        ");
        $stmt->bindParam(":thread_id", $thread_id);
        $stmt->bindParam(":cid", $cid);
        $stmt->execute();
        $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $timestamp = 0;
        array_shift($posts);
        foreach($posts as $key => &$post){
            if (!$show_hidden && $post['hidden']) {
                unset($posts[$key]);
                continue;
            }
            $user = \User::find($post['user_id']);
            if ($user){
                $post['user_name'] = $user->getFullName();
                $post['avatar'] = \Avatar::getAvatar($post['user_id'])->getImageTag(\Avatar::SMALL);
                if ($timestamp < strtotime($post['mkdate'])) {$timestamp = strtotime($post['mkdate']);}
                $post['date'] = date('H:i', strtotime($post['mkdate'])).' Uhr, am '.date('d.m.Y', strtotime($post['mkdate']));
                if ($post['user_id'] == $uid) {$post['own_post'] = true;} else {$post['own_post'] = false;} 
            }
            else  {
                unset($posts[$key]);
            }
        }

        return array('posts'=>$posts, 'timestamp' => $timestamp);
    }

    public function findPost($thread_id, $post_id, $cid)
    {
        $db = \DBManager::get();
        $stmt = $db->prepare("
            SELECT
                *
            FROM
                mooc_posts
            WHERE
                thread_id = :thread_id
            AND
                post_id = :post_id
            AND
                seminar_id = :cid
            LIMIT
                1
        ");
        $stmt->bindParam(":thread_id", $thread_id);
        $stmt->bindParam(":post_id", $post_id);
        $stmt->bindParam(":cid", $cid);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function alterPost($thread_id, $post_id, $cid, $content)
    {
        $db = \DBManager::get();
        $stmt = $db->prepare("
            UPDATE
                mooc_posts
            SET
                content = :content
            WHERE
                thread_id = :thread_id
            AND
                post_id = :post_id
            AND
                seminar_id = :cid
            LIMIT
                1
        ");
        $stmt->bindParam(":content", $content);
        $stmt->bindParam(":thread_id", $thread_id);
        $stmt->bindParam(":post_id", $post_id);
        $stmt->bindParam(":cid", $cid);
        $stmt->execute();
    }

    public function getNextPostId($thread_id, $cid)
    {
        $db = \DBManager::get();
        $stmt = $db->prepare("
            SELECT
                post_id
            FROM
                mooc_posts
            WHERE
                seminar_id = :cid
            AND 
                thread_id = :thread_id
            ORDER BY
                post_id DESC
            LIMIT
                1
        ");
        $stmt->bindParam(":cid", $cid);
        $stmt->bindParam(":thread_id", $thread_id);
        $stmt->execute();
        $post_id = $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);

        return $post_id[0] + 1;
    }

    public function getThreadIds($cid)
    {
        $db = \DBManager::get();
        $stmt = $db->prepare("
            SELECT
                thread_id, content
            FROM
                mooc_posts
            WHERE
                seminar_id = :cid
            AND
                post_id = 0
            GROUP BY
                thread_id
        ");
        $stmt->bindParam(":cid", $cid);
        $stmt->execute();
        $thread_ids = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $thread_ids;
    }

    public function newThreadId($cid)
    {
        $db = \DBManager::get();
        $stmt = $db->prepare("
            SELECT
                thread_id
            FROM
                mooc_posts
            WHERE
                seminar_id = :cid
            ORDER BY
                thread_id DESC
            LIMIT
                1
        ");
        $stmt->bindParam(":cid", $cid);
        $stmt->execute();
        $thread_id = $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);

        return $thread_id[0] + 1;
    }

    public function denyNobodyPost()
    {
        return $this->content['user_id'] != 'nobody';
    }

    public function hidePost($thread_id, $post_id, $cid, $hide = 1)
    {
        $db = \DBManager::get();
        $stmt = $db->prepare("
            UPDATE
                mooc_posts
            SET
                hidden = :hide
            WHERE
                thread_id = :thread_id
            AND
                post_id = :post_id
            AND
                seminar_id = :cid
            LIMIT
                1
        ");
        $stmt->bindParam(":hide", $hide);
        $stmt->bindParam(":thread_id", $thread_id);
        $stmt->bindParam(":post_id", $post_id);
        $stmt->bindParam(":cid", $cid);
        return $stmt->execute();
    }

}
