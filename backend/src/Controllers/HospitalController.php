<?php

declare(strict_types=1);

namespace BloodMatch\Controllers;

use BloodMatch\Config\Database;
use BloodMatch\Utils\Response;

final class HospitalController
{
    public function index(): void
    {
        $db = Database::pdo();
        $stmt = $db->prepare('
            SELECT id, name, type, municipality_name, location_id
            FROM hospitals
            ORDER BY type DESC, name ASC
        ');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        // Group them for frontend convenience if needed, or let frontend do it
        Response::success(['hospitals' => $rows]);
    }
}
