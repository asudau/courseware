<?php

class ProgressController extends CoursewareStudipController
{
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        if ($this->container['current_user']->isNobody()) {
            return false;
        }
    }

    public function index_action()
    {
        PageLayout::addStylesheet($this->plugin->getPluginURL().'/assets/static/courseware.css');

        if (Navigation::hasItem('/course/mooc_courseware/progress')) {
            Navigation::activateItem('/course/mooc_courseware/progress');
        }

        $blocks = \Mooc\DB\Block::findBySQL('seminar_id = ? ORDER BY id, position', array($this->plugin->getCourseId()));
        $bids = array_map(function ($block) { return (int) $block->id; }, $blocks);
        $progress = array_reduce(
            \Mooc\DB\UserProgress::findBySQL('block_id IN (?) AND user_id = ?', array($bids, $this->plugin->getCurrentUserId())),
            function ($memo, $item) {
                $memo[$item->block_id] = array(
                    'grade' => $item->grade,
                    'max_grade' => $item->max_grade,
                    'date' => $item->chdate
                );

                return $memo;
            },
            array());

        $grouped = array_reduce(
            \Mooc\DB\Block::findBySQL('seminar_id = ? ORDER BY id, position', array($this->plugin->getCourseId())),
            function ($memo, $item) {
                $memo[$item->parent_id][] = $item->toArray();

                return $memo;
            },
            array());

        $this->courseware = current($grouped['']);
        $this->buildTree($grouped, $progress, $this->courseware);
    }

    private function buildTree($grouped, $progress, &$root)
    {
        $this->addChildren($grouped, $root);

        if ($root['type'] !== 'Section') {
            foreach ($root['children'] as &$child) {
                $this->buildTree($grouped, $progress, $child);
            }
            $root['progress'] = $this->computeProgress($root);
            $root['date'] = $this->setDate($progress, $root);
        } else {
            $root['children'] = $this->addChildren($grouped, $root);
            if ($root['children']) {
                $grades = array_map(
                    function ($block) use ($progress) {
                        return (float) $progress[$block['id']]['grade'];
                    },
                    $root['children']
                );
                $maxGrades = array_map(
                    function ($block) use ($progress) {
                        if ($progress[$block['id']]['max_grade'] == null) {
                            return 1;
                        }
                        return (float) $progress[$block['id']]['max_grade'];
                    },
                    $root['children']
                );

                if (array_sum($maxGrades) > 0) {
                    $root['progress'] = array_sum($grades) / array_sum($maxGrades);
                } else {
                    $root['progress'] = 0;
                }
            } else {
                $root['progress'] = 1;
            }
        }
    }

    private function addChildren($grouped, &$parent)
    {
        $parent['children'] = array_filter(
            isset($grouped[$parent['id']]) ? $grouped[$parent['id']] : array(),
            function ($item) {
                return $item['publication_date'] <= time();
            });

        return $parent['children'];
    }

    private function computeProgress(&$block)
    {
        if (!sizeof($block['children'])) {
            return 0;
        }

        return array_sum(
                array_map(
                    function ($section) {return $section['progress']; },
                    $block['children'])
            ) / sizeof($block['children']
        );
    }

    private function setDate($progress, &$block)
    {
        if (!sizeof($block['children'])) {
            return null;
        }
        $date = date('');
        foreach ($block['children'] as $section) {
            foreach($section['children']as $blocks) {
               if ($b = $progress[$blocks['id']]) {
                    if ($date < date($b['date'])){ 
                        $date = date($b['date']);
                    }
                }
            }
        }

        return $date;
    }
}
