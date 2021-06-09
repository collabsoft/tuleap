<?php
/**
 * Copyright (c) Enalean, 2021 - Present. All Rights Reserved.
 * Copyright (c) STMicroelectronics, 2006. All Rights Reserved.
 *
 * Originally written by Mahmoud MAALEJ, 2006. STMicroelectronics.
 *
 * This file is a part of Codendi.
 *
 * Codendi is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Codendi is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Codendi. If not, see <http://www.gnu.org/licenses/>.
 */

use Tuleap\DB\DBFactory;

class GraphOnTrackersV5_Chart_BarDataBuilder extends ChartDataBuilderV5
{
    /**
     * build pie chart properties
     *
     * @param GraphOnTrackersV5_Engine_Bar $engine
     */
    public function buildProperties($engine): void
    {
        $this->buildParentProperties($engine);
        $source_field = $this->buildSourceField();

        if (! $source_field->userCanRead() || $this->getArtifactIds() === '') {
            return;
        }

        $group_by_field     = null;
        $has_group_by_field = $from_group = $group_group = $order_by = '';
        if ($this->isAGroupBarChart()) {
            $group_by_field = $this->buildGroupByField();
            \assert($group_by_field instanceof Tracker_FormElement_Field_List);
            if ($group_by_field && $group_by_field->userCanRead()) {
                $has_group_by_field = ', ' . $group_by_field->getQuerySelect();
                $from_group         = '  ' . $group_by_field->getQueryFrom();
                $group_group        = ', ' . $group_by_field->getQueryGroupBy();
                $order_by           = $group_by_field->getQueryOrderby();
            } else {
                $order_by = $source_field->getQueryOrderby();
            }
        }

        $sql    = $this->buildChartQuery($source_field, $has_group_by_field, $from_group, $group_group, $order_by);
        $result = $this->getQueryResult($sql);

        $this->buildChartEngine($result, $has_group_by_field, $group_by_field, $engine, $source_field);
    }

    /**
     * for testing purpose
     */
    protected function getFormElementFactory(): Tracker_FormElementFactory
    {
        return Tracker_FormElementFactory::instance();
    }

    protected function buildSourceField(): Tracker_FormElement_Field_List
    {
        $source_field = $this->getFormElementFactory()->getUsedListFieldById($this->getTracker(), $this->chart->getField_base());
        if (! $source_field) {
            throw new \Tuleap\GraphOnTrackersV5\DataTransformation\ChartFieldNotFoundException($this->chart->getTitle());
        }
        \assert($source_field instanceof Tracker_FormElement_Field_List);

        return $source_field;
    }

    /**
     * for testing purpose
     */
    protected function getFieldGroupId(): int
    {
        return (int) $this->chart->getField_group();
    }

    /**
     * for testing purpose
     */
    protected function getFieldBaseId(): int
    {
        return (int) $this->chart->getField_base();
    }

    /**
     * for testing purpose
     */
    protected function getArtifactIds(): string
    {
        return $this->artifacts['id'];
    }

    /**
     * for testing purpose
     */
    protected function getArtifactsLastChangesetIds(): string
    {
        return $this->artifacts['last_changeset_id'];
    }

    /**
     * for testing purpose
     */
    protected function getQueryResult(string $sql): array
    {
        $db = DBFactory::getMainTuleapDBConnection()->getDB();
        return $db->run($sql);
    }

    /**
     * for testing purpose
     */
    protected function buildParentProperties(GraphOnTrackersV5_Engine_Bar $engine): void
    {
        parent::buildProperties($engine);
    }

    private function buildChartEngine(
        array $result,
        string $has_group_by_field,
        ?Tracker_FormElement_Field_List $group_by_field,
        GraphOnTrackersV5_Engine_Bar $engine,
        Tracker_FormElement_Field_List $source_field
    ): void {
        $engine->data = [];

        foreach ($result as $data) {
            $color = $this->getColor($data);
            if ($has_group_by_field && $group_by_field) {
                $engine->data[$data[$source_field->name]][$data[$group_by_field->name]] = $data['nb'];
                $this->buildGroupChartProperties($color, $engine, $data, $source_field, $group_by_field);
            } else {
                $engine->colors[] = $color;
                $engine->data[]   = $data['nb'];
            }
            $engine->legend[$data[$source_field->name]] = $GLOBALS['Language']->getText('global', 'none');

            if ($data[$source_field->name] !== null) {
                $engine->legend[$data[$source_field->name]] = $source_field->fetchRawValue($data[$source_field->name]);
            }
        }
    }

    private function buildGroupByField(): ?Tracker_FormElement_Field
    {
        return $this->getFormElementFactory()->getFormElementById($this->getFieldGroupId());
    }

    /**
     * @param string | array $color
     */
    private function buildGroupChartProperties(
        $color,
        GraphOnTrackersV5_Engine_Bar $engine,
        array $data,
        Tracker_FormElement_Field_List $source_field,
        Tracker_FormElement_Field_List $group_by_field
    ): void {
        $none                                         = $GLOBALS['Language']->getText('global', 'none');
        $engine->colors[$data[$source_field->name]]   = $color;
        $engine->xaxis[$data[$group_by_field->name]]  = $none;
        $engine->labels[$data[$group_by_field->name]] = $none;

        if ($data[$group_by_field->name] !== null) {
            $engine->xaxis[$data[$group_by_field->name]]  = $group_by_field->fetchRawValue($data[$group_by_field->name]);
            $engine->labels[$data[$group_by_field->name]] = $group_by_field->fetchRawValue($data[$group_by_field->name]);
        }
    }

    private function isAGroupBarChart(): bool
    {
        return $this->getFieldGroupId() !== $this->getFieldBaseId();
    }

    private function buildChartQuery(
        Tracker_FormElement_Field_List $source_field,
        string $has_group_by_field,
        string $from_group,
        string $group_group,
        string $order_by
    ): string {
        $select = " SELECT count(a.id) AS nb, " . $source_field->getQuerySelectWithDecorator() . $has_group_by_field;
        $from   = " FROM tracker_artifact AS a
                         INNER JOIN tracker_changeset AS c ON (c.artifact_id = a.id) " .
            $source_field->getQueryFromWithDecorator() .
            $from_group;
        $where  = " WHERE a.id IN (" . $this->getArtifactIds() . ")
                      AND c.id IN (" . $this->getArtifactsLastChangesetIds() . ") ";

        return $select . $from . $where . ' GROUP BY ' . $source_field->getQueryGroupBy() . $group_group . ' ORDER BY ' . $order_by;
    }
}
