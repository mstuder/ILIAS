<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilOrgUnitExporter
 * @author: Oskar Truffer <ot@studer-raimann.ch>
 * @author: Martin Studer <ms@studer-raimann.ch>
 */
class ilOrgUnitExporter extends ilCategoryExporter
{
    private ilTree $tree;

    public function __construct()
    {
        global $DIC;
        parent::__construct();
        $this->tree = $DIC['tree'];
    }

    final public function simpleExport(int $orgu_ref_id): ilXmlWriter
    {
        $nodes = $this->getStructure($orgu_ref_id);
        $writer = new ilXmlWriter();
        $writer->xmlHeader();
        $writer->xmlStartTag("OrgUnits");
        foreach ($nodes as $node_ref_id) {
            $orgu = new ilObjOrgUnit($node_ref_id);
            if ($orgu->getRefId() == ilObjOrgUnit::getRootOrgRefId()) {
                continue;
            }
            $attributes = $this->getAttributesForOrgu($orgu);
            $writer->xmlStartTag("OrgUnit", $attributes);
            $writer->xmlElement("external_id", null, $this->getExternalId($node_ref_id));
            $writer->xmlElement("title", null, $orgu->getTitle());
            $writer->xmlElement("description", null, $orgu->getDescription());
            $writer->xmlEndTag("OrgUnit");
        }
        $writer->xmlEndTag("OrgUnits");

        return $writer;
    }

    final protected function getExternalId(int $orgu_ref_id): string
    {
        $import_id = ilObjOrgunit::_lookupImportId(ilObjOrgUnit::_lookupObjectId($orgu_ref_id));

        return $import_id ?: $this->buildExternalId($orgu_ref_id);
    }

    final protected function buildExternalId(int $orgu_ref_id): string
    {
        return "orgu_" . CLIENT_ID . "_" . $orgu_ref_id;
    }

    final public function simpleExportExcel(int $orgu_ref_id): void
    {
        // New File and Sheet
        $file_name = "org_unit_export_" . $orgu_ref_id;
        $worksheet = new ilExcel();
        $worksheet->addSheet('org_units');
        $row = 1;

        // Headers
        $worksheet->setCell($row, 0, "ou_id");
        $worksheet->setCell($row, 1, "ou_id_type");
        $worksheet->setCell($row, 2, "ou_parent_id");
        $worksheet->setCell($row, 3, "ou_parent_id_type");
        $worksheet->setCell($row, 4, "reference_id");
        $worksheet->setCell($row, 5, "title");
        $worksheet->setCell($row, 6, "description");
        $worksheet->setCell($row, 7, "action");

        // Rows
        $nodes = $this->getStructure($orgu_ref_id);

        foreach ($nodes as $node) {
            $orgu = new ilObjOrgUnit($node);
            if ($orgu->getRefId() == ilObjOrgUnit::getRootOrgRefId()) {
                continue;
            }
            $row++;
            $attrs = $this->getAttributesForOrgu($orgu);
            $worksheet->setCell($row, 0, $attrs["ou_id"]);
            $worksheet->setCell($row, 1, $attrs["ou_id_type"]);
            $worksheet->setCell($row, 2, $attrs["ou_parent_id"]);
            $worksheet->setCell($row, 3, $attrs["ou_parent_id_type"]);
            $worksheet->setCell($row, 4, $orgu->getRefId());
            $worksheet->setCell($row, 5, $orgu->getTitle());
            $worksheet->setCell($row, 6, $orgu->getDescription());
            $worksheet->setCell($row, 7, "create");
        }
        $worksheet->sendToClient($file_name);
    }

    final public function sendAndCreateSimpleExportFile(): array
    {
        $orgu_id = ilObjOrgUnit::getRootOrgId();
        $orgu_ref_id = ilObjOrgUnit::getRootOrgRefId();

        ilExport::_createExportDirectory($orgu_id, "xml", "orgu");
        $export_dir = ilExport::_getExportDirectory($orgu_id, "xml", "orgu");
        $ts = time();

        // Workaround for test assessment
        $sub_dir = $ts . '__' . IL_INST_ID . '__' . "orgu" . '_' . $orgu_id . "";
        $new_file = $sub_dir . '.zip';

        $export_run_dir = $export_dir . "/" . $sub_dir;
        ilFileUtils::makeDirParents($export_run_dir);

        $writer = $this->simpleExport($orgu_ref_id);
        $writer->xmlDumpFile($export_run_dir . "/manifest.xml", false);

        // zip the file
        ilFileUtils::zip($export_run_dir, $export_dir . "/" . $new_file);
        ilFileUtils::delDir($export_run_dir);

        // Store info about export
        $exp = new ilExportFileInfo($orgu_id);
        $exp->setVersion(ILIAS_VERSION_NUMERIC);
        $exp->setCreationDate(new ilDateTime($ts, IL_CAL_UNIX));
        $exp->setExportType('xml');
        $exp->setFilename($new_file);
        $exp->create();

        ilFileDelivery::deliverFileLegacy(
            $export_dir . "/" . $new_file,
            $new_file
        );

        return array(
            "success" => true,
            "file" => $new_file,
            "directory" => $export_dir,
        );
    }

    private function getStructure(int $root_node_ref): array
    {
        $open = array($root_node_ref);
        $closed = array();
        while (count($open)) {
            $current = array_shift($open);
            $closed[] = $current;
            foreach ($this->tree->getChildsByType($current, "orgu") as $new) {
                if (in_array($new["child"], $closed, true) === false && in_array($new["child"], $open, true) === false) {
                    $open[] = $new["child"];
                }
            }
        }

        return $closed;
    }

    private function getAttributesForOrgu(ilObjOrgUnit $orgu): array
    {
        $parent_ref = $this->tree->getParentId($orgu->getRefId());
        if ($parent_ref != ilObjOrgUnit::getRootOrgRefId()) {
            $ou_parent_id = $this->getExternalId($parent_ref);
        } else {
            $ou_parent_id = "__ILIAS";
        }
        // Only the ref id is guaranteed to be unique.
        $ref_id = $orgu->getRefId();
        $attr = array("ou_id" => $this->getExternalId($ref_id),
                      "ou_id_type" => "external_id",
                      "ou_parent_id" => $ou_parent_id,
                      "ou_parent_id_type" => "external_id",
                      "action" => "create"
        );

        return $attr;
    }
}
