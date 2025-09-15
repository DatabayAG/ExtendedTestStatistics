<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use ILIAS\ResourceStorage\Identification\ResourceIdentification;

class ilExteStatExportRepo
{
    public const EXPORT_TABLE = 'etstat_exports';

    public function __construct(
        private readonly \ilDBInterface $db
    ) {
    }

    public function store(
        int $object_id,
        ResourceIdentification $rid
    ): void {
        $this->db->insert(
            self::EXPORT_TABLE,
            [
                'obj_id' => [
                    \ilDBConstants::T_INTEGER,
                    $object_id
                ],
                'rid' => [
                    \ilDBConstants::T_TEXT,
                    $rid->serialize()
                ],
            ]
        );
    }

    public function delete(
        ResourceIdentification $rid
    ): void {
        $this->db->manipulateF(
            'DELETE FROM ' . self::EXPORT_TABLE . ' WHERE rid = %s',
            [\ilDBConstants::T_TEXT],
            [$rid->serialize()]
        );
    }

    public function has(
        int $object_id,
        ResourceIdentification $rid,
    ): bool {
        return !empty($this->db->fetchAll(
            $this->db->queryF(
                'SELECT * FROM ' . self::EXPORT_TABLE . ' WHERE obj_id = %s AND rid = %s',
                [\ilDBConstants::T_INTEGER, \ilDBConstants::T_TEXT],
                [$object_id, $rid->serialize()]
            )
        )
        );
    }

    public function getFor(
        int $object_id
    ): array {
        return $this->db->fetchAll(
            $this->db->queryF(
                'SELECT * FROM ' . self::EXPORT_TABLE . ' WHERE obj_id = %s',
                [\ilDBConstants::T_INTEGER],
                [$object_id]
            )
        );
    }
}
