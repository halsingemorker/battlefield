<?php
// ══════════════════════════════════════════════════════════════
// api.php  — ConcertsDB backend  (v3.5)
// ══════════════════════════════════════════════════════════════

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ── DB CONNECTION ─────────────────────────────────────────────
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]);
    exit;
}

// ── ROUTER ────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$action = '';

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
} elseif ($method === 'POST') {
    $body   = file_get_contents('php://input');
    $data   = json_decode($body, true) ?? [];
    $action = $data['action'] ?? '';
}

try {
    switch ($action) {

        // ── READ ──────────────────────────────────────────────
        case 'load_all':
        case 'all':
            echo json_encode([
                'events'      => $pdo->query("SELECT * FROM events ORDER BY date DESC")->fetchAll(),
                'bands'       => $pdo->query("SELECT * FROM bands ORDER BY band_name")->fetchAll(),
                'event_bands' => $pdo->query("SELECT * FROM event_bands")->fetchAll(),
                'attendance'  => $pdo->query("SELECT * FROM attendance")->fetchAll(),
                'people'      => $pdo->query("SELECT * FROM people ORDER BY person_name")->fetchAll(),
            ]);
            break;

        // ── REVIEW SWEEP ──────────────────────────────────────
        case 'review_sweep':
            $today = date('Y-m-d');
            $stmt  = $pdo->prepare(
                "UPDATE events SET status = 'review'
                 WHERE status = 'upcoming'
                   AND date < ?
                   AND date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'"
            );
            $stmt->execute([$today]);
            echo json_encode(['updated' => $stmt->rowCount()]);
            break;

        // ── ADD EVENT ─────────────────────────────────────────
        case 'add_event':
            $pdo->beginTransaction();
            try {
                $event_id = insertEvent($pdo, $data);
                processBands($pdo, $event_id, $data);
                $pdo->commit();
                echo json_encode(['ok' => true, 'event_id' => $event_id]);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        // ── EDIT EVENT ────────────────────────────────────────
        case 'edit_event':
            $event_id = intval($data['event_id'] ?? 0);
            if (!$event_id) throw new Exception('event_id is required');

            $pdo->beginTransaction();
            try {
                updateEvent($pdo, $event_id, $data);
                $pdo->prepare("DELETE FROM attendance WHERE event_id = ?")->execute([$event_id]);
                $pdo->prepare("DELETE FROM event_bands WHERE event_id = ?")->execute([$event_id]);
                processBands($pdo, $event_id, $data);
                $pdo->commit();
                echo json_encode(['ok' => true]);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        // ── ADD PERSON ────────────────────────────────────────
        case 'add_person':
            $name = trim($data['person_name'] ?? '');
            $code = trim($data['person_code'] ?? '');
            if (!$name || !$code) throw new Exception('person_name and person_code are required');

            $stmt = $pdo->prepare(
                "INSERT INTO people (person_code, person_name, is_maintainer) VALUES (?, ?, 'no')"
            );
            $stmt->execute([$code, $name]);
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => "Unknown action: {$action}"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// ══════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ══════════════════════════════════════════════════════════════

/**
 * Insert a new row into events. Returns the new event_id.
 * v3.5: replaced setlistfm_url with event_url + ticket_url.
 */
function insertEvent(PDO $pdo, array $d): int {
    $stmt = $pdo->prepare("
        INSERT INTO events
            (date, city, country, venue, festival, tour, status,
             ticket_price_original, ticket_currency, price_note,
             event_url, ticket_url, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        sanitiseDate($d['date']    ?? ''),
        trim($d['city']            ?? ''),
        trim($d['country']         ?? ''),
        trim($d['venue']           ?? ''),
        trim($d['festival']        ?? ''),
        trim($d['tour']            ?? ''),
        sanitiseStatus($d['status'] ?? 'upcoming'),
        trim($d['ticket_price_original'] ?? ''),
        trim($d['ticket_currency']       ?? ''),
        trim($d['price_note']            ?? ''),
        trim($d['event_url']             ?? ''),
        trim($d['ticket_url']            ?? ''),
        trim($d['notes']                 ?? ''),
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Update an existing event row.
 * v3.5: replaced setlistfm_url with event_url + ticket_url.
 */
function updateEvent(PDO $pdo, int $event_id, array $d): void {
    $stmt = $pdo->prepare("
        UPDATE events SET
            date                  = ?,
            city                  = ?,
            country               = ?,
            venue                 = ?,
            festival              = ?,
            tour                  = ?,
            status                = ?,
            ticket_price_original = ?,
            ticket_currency       = ?,
            price_note            = ?,
            event_url             = ?,
            ticket_url            = ?,
            notes                 = ?
        WHERE event_id = ?
    ");
    $stmt->execute([
        sanitiseDate($d['date']    ?? ''),
        trim($d['city']            ?? ''),
        trim($d['country']         ?? ''),
        trim($d['venue']           ?? ''),
        trim($d['festival']        ?? ''),
        trim($d['tour']            ?? ''),
        sanitiseStatus($d['status'] ?? 'past'),
        trim($d['ticket_price_original'] ?? ''),
        trim($d['ticket_currency']       ?? ''),
        trim($d['price_note']            ?? ''),
        trim($d['event_url']             ?? ''),
        trim($d['ticket_url']            ?? ''),
        trim($d['notes']                 ?? ''),
        $event_id,
    ]);
}

/**
 * Process band rows from form data.
 * v3.4: added hated_it field (always 'False' for new entries —
 *       hated_it is set manually or via edit, never on first insert).
 *
 * Attendance logic:
 *   was_at_event = True  if person_code is in event_attendees[]
 *   did_see      = True  if person_code is in band.attendees[]
 *   hated_it     = False always on insert (corrected manually later)
 *   did_see is forced False if was_at_event is False
 */
function processBands(PDO $pdo, int $event_id, array $d): void {
    $bands           = $d['bands']           ?? [];
    $event_attendees = $d['event_attendees'] ?? [];

    $people = $pdo->query("SELECT person_code, person_name FROM people")->fetchAll();

    foreach ($bands as $band) {
        $band_name   = trim($band['band_name'] ?? '');
        if ($band_name === '') continue;

        $band_id     = $band['band_id'] ? intval($band['band_id']) : null;
        $band_status = in_array($band['status'], ['performed','cancelled']) ? $band['status'] : 'performed';
        $did_see_codes = $band['attendees'] ?? [];

        // Create new band if needed
        if (!$band_id) {
            $existing = $pdo->prepare("SELECT band_id FROM bands WHERE band_name = ?");
            $existing->execute([$band_name]);
            $row = $existing->fetch();
            if ($row) {
                $band_id = (int) $row['band_id'];
            } else {
                $ins = $pdo->prepare("INSERT INTO bands (band_name, metallum_url, spotify_url, setlistfm_url) VALUES (?, NULL, NULL, NULL)");
                $ins->execute([$band_name]);
                $band_id = (int) $pdo->lastInsertId();
            }
        }

        // Insert event_bands row
        $eb_stmt = $pdo->prepare(
            "INSERT INTO event_bands (event_id, band_id, status) VALUES (?, ?, ?)"
        );
        $eb_stmt->execute([$event_id, $band_id, $band_status]);
        $event_band_id = (int) $pdo->lastInsertId();

        // Insert attendance rows for every person
        $att_stmt = $pdo->prepare("
            INSERT INTO attendance
                (event_id, event_band_id, band_id, person_code, person_name,
                 was_at_event, did_see, hated_it, rating, rating_notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'False', NULL, NULL)
        ");

        foreach ($people as $person) {
            $code         = $person['person_code'];
            $name         = $person['person_name'];
            $was_at_event = in_array($code, $event_attendees) ? 'True' : 'False';
            $did_see      = in_array($code, $did_see_codes)   ? 'True' : 'False';
            if ($was_at_event === 'False') $did_see = 'False';

            $att_stmt->execute([
                $event_id,
                $event_band_id,
                $band_id,
                $code,
                $name,
                $was_at_event,
                $did_see,
            ]);
        }
    }
}

/**
 * Validate and sanitise date string.
 */
function sanitiseDate(string $d): string {
    $d = trim($d);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return $d;
    if (preg_match('/^\d{4}\.\?$/', $d)) return $d;
    return $d;
}

/**
 * Whitelist event status values.
 */
function sanitiseStatus(string $s): string {
    $allowed = ['past', 'upcoming', 'review', 'cancelled'];
    return in_array($s, $allowed) ? $s : 'upcoming';
}
