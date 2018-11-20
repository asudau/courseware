<?php

namespace Mooc\DB;

/**
 * @author  <mlunzena@uos.de>
 *
 * @property int     $id
 * @property string  $type
 * @property string  $sub_type
 * @property int     $parent_id
 * @property Block   $parent
 * @property Block[] $children
 * @property string  $seminar_id
 * @property \Course $course
 * @property string  $title
 * @property int     $position
 * @property int     $publication_date
 * @property int     $chdate
 * @property int     $mkdate
 */
class Block extends \SimpleORMap implements \Serializable
{
    public $errors = array();

    protected static function configure($config = array())
    {
        $config['db_table'] = 'mooc_blocks';

        $config['belongs_to']['parent'] = array(
            'class_name' => 'Mooc\\DB\\Block',
            'foreign_key' => 'parent_id'
        );

        $config['belongs_to']['course'] = array(
            'class_name' => '\\Course',
            'foreign_key' => 'seminar_id'
        );

        $config['has_many']['children'] = array(
            'class_name' => 'Mooc\\DB\\Block',
            'assoc_foreign_key' => 'parent_id',
            'assoc_func' => 'findByParent_id',
            'on_delete' => 'delete',
            'on_store' => 'store'
        );

        $config['registered_callbacks']['before_create'] = array(
            'ensureSeminarId',
            'ensurePositionId'
        );

        $config['registered_callbacks']['before_store'] = array(
            'validate'
        );

        $config['registered_callbacks']['after_delete'] = array(
            'destroyFields',
            'destroyUserProgress',
            'updatePositionsAfterDelete'
        );

        parent::configure($config);
    }

    /**
     * Give primary key of record as param to fetch
     * corresponding record from db if available, if not preset primary key
     * with given value. Give null to create new record.
     *
     * @param mixed $id primary key of table
     */
    public function __construct($id = null)
    {
        parent::__construct($id);
    }

    /**
     * Sets the seminar id of the block just before storing it to the database
     * if the current course information is stored in the session.
     */
    protected function ensureSeminarId()
    {
        // TODO: (mlunzena) we cannot be sure to have a
        // SessionSeminar, so we must get that value somewhere else
        if ($this->seminar_id === null && isset($GLOBALS['SessionSeminar'])) {
            $this->seminar_id = $GLOBALS['SessionSeminar'];
        } elseif ($this->seminar_id === '') {
            // workaround to allow blocks that are not associated with a course
            $this->seminar_id = null;
        }
    }

    /**
     * Calculates the position of a new block by counting the already existing
     * blocks on the same level.
     */
    protected function ensurePositionId()
    {
        // Check for new and old version
        if ($this->parent_id !== null && !$this->position) {
            $this->position = static::countBySQL(
                'parent_id = ? ORDER BY position ASC',
                array($this->parent_id)
            );
        }
    }

    public function getNewPosition($parent_id)
    {
        return static::countBySQL( 'parent_id = ? ORDER BY position ASC', array($parent_id));
    }

    public function getAncestors()
    {
        $ancestors = array();
        $cursor = $this;
        while ($cursor->parent) {
            $ancestors[] = $cursor->parent;
            $cursor = $cursor->parent;
        }

        return array_reverse($ancestors);
    }

    public function nextSibling()
    {
        return static::findOneBySQL('parent_id = ? AND position > ? ORDER BY position ASC', array($this->parent_id, $this->position));
    }

    public function previousSibling()
    {
        return static::findOneBySQL('parent_id = ? AND position < ? ORDER BY position DESC', array($this->parent_id, $this->position));
    }

    public static function findByParent_id($id)
    {
        return static::findBySQL('parent_id = ? ORDER BY position ASC', array($id));
    }

    public static function findCourseware($cid)
    {
        return current(self::findBySQL('seminar_id = ? AND type = ? LIMIT 1', array($cid, 'Courseware')));
    }

    public function getCoursewareOfThisBlock()
    {
        return static::findOneBySQL('seminar_id = ? AND type = ? LIMIT 1', array($this->seminar_id, 'Courseware'));
    }

    /**
     * Find all Block of given types in a single course.
     *
     * @param string $cid   the ID of the course
     * @param mixed  $types either a string containing a single block type
     *                      or an array of strings containing block types
     *
     * @return array an array of Block instances of those types in
     *               that course
     */
    public static function findInCourseByType($cid, $types = array())
    {
        if (!is_array($types)) {
            $types = (array) $types;
        }

        return static::findBySQL('seminar_id = ? AND type IN (?) ORDER BY position ASC', array($cid, $types));
    }

    // enumerate all known structural block classes
    private static $structural_block_classes = array('Courseware', 'Chapter', 'Subchapter', 'Section');

    /**
     * Return all known structural block classes.
     *
     * @return array all known structure classes
     */
    public static function getStructuralBlockClasses()
    {
        return self::$structural_block_classes;
    }

    /**
     * Returns whether this block is a structural block.
     *
     * @return bool `true` if it is a structural block, `false` otherwise
     */
    public function isStructuralBlock()
    {
        return in_array($this->type, self::$structural_block_classes);
    }

    /**
     * checks, if block is valid.
     *
     * @return bool true or false
     */
    public function validate()
    {
        if (!strlen(trim($this->title))) {
            $this->errors[] = 'Title may not be empty.';

            return false;
        }

        return true;
    }

    /**
     * Remove associated Fields on delete.
     */
    public function destroyFields()
    {
        Field::deleteBySQL('block_id = ?', array($this->id));
    }

    /**
     * Remove associated UserProgress on delete.
     */
    public function destroyUserProgress()
    {
        UserProgress::deleteBySQL('block_id = ?', array($this->id));
    }

    /**
     * Reflects changes in position if a block on one level is deleted.
     */
    public function updatePositionsAfterDelete()
    {
        if (!$this->parent) {
            return;
        }

        $db = \DBManager::get();
        $stmt = $db->prepare(sprintf(
            'UPDATE
              %s
            SET
              position = position - 1
            WHERE
              parent_id = :parent_id AND
              position > :position',
            $this->db_table
        ));
        $stmt->bindValue(':parent_id', $this->parent->id);
        $stmt->bindValue(':position', $this->position);
        $stmt->execute();
    }

    /**
     * Update child sorting.
     *
     * @param array $positions the new sort order
     */
    public function updateChildPositions($positions)
    {
        $query = sprintf(
            'UPDATE %s SET position = FIND_IN_SET(id, ?) WHERE parent_id = ?',
            $this->db_table);
        $args = array(join(',', $positions), $this->id);

        $db = \DBManager::get();
        $st = $db->prepare($query);
        $st->execute($args);
    }

    /**
     * @param int $timestamp
     *
     * @return bool
     */
    public function isPublished($timestamp = null)
    {
        if (is_null($timestamp)) {
            $timestamp = time();
        }

        // check if parent blocks are published
        if ($this->parent && !$this->parent->isPublished($timestamp)) {
            return false;
        }

        // check if block is published
        return $this->publication_date <= $timestamp;
    }

    public function serialize()
    {
        if ($this->isDirty()) {
            throw new \RuntimeException('Cannot serialize dirty Block instances.');
        }

        return serialize(array($this->content, $this->is_new));
    }

    public function unserialize($serialized)
    {
        static::__construct();

        list($data, $is_new) = unserialize($serialized);
        $this->setData($data, true);
        $this->setNew($is_new);
    }

    // has the given user completed (progress = 100%) this content
    // block or all of its descendent content blocks
    public function hasUserCompleted($uid)
    {
        if ($uid === 'nobody') {
            // TODO: (mlunzena) oder lieber true?
            return false;
        } elseif ($this->isStructuralBlock()) {
            // structural blocks compute their score using their non-structural descendants

            // empty structural blocks are not completed
            if (sizeof($this->children) === 0) {
                return true;
            }

            $completings = array();
            foreach ($this->children as $child) {
                $completings[] = $child->hasUserCompleted($uid);
            }
            $status = array_search(false, array_flatten($completings)) === false;

            return $status;
        } else {
            $progress = new UserProgress(array($this->id, $uid));

            return $progress->getPercentage() === 1;
        }
    }

    public function getContentChildren()
    {
        return $this->children->filter(function ($child) {
            return !in_array($child->type, Block::getStructuralBlockClasses());
        });
    }

    public function getStructuralChildren()
    {
        return $this->children->filter(function ($child) {
            return in_array($child->type, Block::getStructuralBlockClasses());
        });
    }

    public function getUUID()
    {
        global $STUDIP_INSTALLATION_ID;

        $hash = sha1($STUDIP_INSTALLATION_ID.$this->seminar_id.$this->id);

        return sprintf('%08s-%04s-%04x-%04x-%12s',
                       substr($hash, 0, 8),
                       substr($hash, 8, 4),
                       (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,
                       (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
                       substr($hash, 20, 12)
        );
    }
    
    public function belongesToCourse($cid)
    {
        return $this->seminar_id == $cid;
    }
}
