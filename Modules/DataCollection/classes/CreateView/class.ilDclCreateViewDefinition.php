<?php

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilDclCreateViewDefinition
 * @author  studer + raimann ag - Team Custom 1 <support-custom1@studer-raimann.ch>
 * @ingroup ModulesDataCollection
 */
class ilDclCreateViewDefinition extends ilPageObject
{
    const PARENT_TYPE = 'dclf';
    protected bool $active = false;
    protected int $table_id;
    /**
     * Cache record views per table-id, key=table-id, value=view definition id
     */
    protected static array $record_view_cache = array();

    /**
     * Get parent type
     */
    public function getParentType() : string
    {
        return self::PARENT_TYPE;
    }

    /**
     * Get all placeholders for table id
     * @return array
     */
    public function getAvailablePlaceholders(): array
    {
        $all = array();

        $tableview = new ilDclTableView($this->getId());
        $table_id = $tableview->getTableId();
        $objTable = ilDclCache::getTableCache($table_id);
        $fields = $objTable->getRecordFields();
        $standardFields = $objTable->getStandardFields();

        foreach ($fields as $field) {
            $all[] = "[" . $field->getTitle() . "]";

            if ($field->getDatatypeId() == ilDclDatatype::INPUTFORMAT_REFERENCE) {
                $all[] = '[dclrefln field="' . $field->getTitle() . '"][/dclrefln]';
            }
            // SW 14.10.2015 http://www.ilias.de/mantis/view.php?id=16874
            //				if ($field->getDatatypeId() == ilDclDatatype::INPUTFORMAT_ILIAS_REF) {
            //					$all[] = '[dcliln field="' . $field->getTitle() . '"][/dcliln]';
            //				}
        }

        foreach ($standardFields as $field) {
            $all[] = "[" . $field->getId() . "]";
        }

        return $all;
    }

    public static function exists(int $id): bool
    {
        return parent::_exists(self::PARENT_TYPE, $id);
    }

    public static function isActive(int $id): bool
    {
        return parent::_lookupActive($id, self::PARENT_TYPE);
    }
}
