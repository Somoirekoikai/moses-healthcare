<?php
require_once __DIR__ . "/../database.php";
require_once __DIR__ . "/../includes/functions.php";

requireRole("doctor");

$doctorStmt = $pdo->prepare(
    "SELECT id, specialty FROM doctors WHERE user_id = ?"
);
$doctorStmt->execute([$_SESSION["user_id"]]);
$doctor = $doctorStmt->fetch();

if (!$doctor) {
    exit("Doctor profile not found. Please contact the administrator.");
}

$success = "";
$error = "";
$csrfToken = $_SESSION["csrf_token"] ?? bin2hex(random_bytes(32));
$_SESSION["csrf_token"] = $csrfToken;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        $error = "Invalid request. Please refresh and try again.";
    } else {
        $appointmentId = filter_input(INPUT_POST, "appointment_id", FILTER_VALIDATE_INT);
        $status = $_POST["status"] ?? "";

        if (!$appointmentId || !in_array($status, ["confirmed", "declined"], true)) {
            $error = "Invalid appointment request.";
        } else {
            $update = $pdo->prepare(
                "UPDATE appointments
                 SET status = ?
                 WHERE id = ? AND doctor_id = ? AND status = 'pending'"
            );
            $update->execute([$status, $appointmentId, $doctor["id"]]);

            $success = $update->rowCount()
                ? "Appointment status updated."
                : "Appointment was not changed. It may already have been processed.";
        }
    }
}

$appointmentsStmt = $pdo->prepare(
    "SELECT appointments.id, appointments.appointment_date,
            appointments.appointment_time, appointments.reason,
            appointments.status, users.full_name AS patient_name,
            users.phone AS patient_phone
     FROM appointments
     JOIN users ON users.id = appointments.patient_id
     WHERE appointments.doctor_id = ?
     ORDER BY appointments.appointment_date DESC, appointments.appointment_time DESC"
);
$appointmentsStmt->execute([$doctor["id"]]);
$appointments = $appointmentsStmt->fetchAll();

$pendingCount = count(array_filter(
    $appointments,
    fn($appointment) => $appointment["status"] === "pending"
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard | Moses Healthcare</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: "Century Gothic", sans-serif; }</style>
</head>
<body class="min-h-screen bg-sky-50 text-slate-800">
    <header class="flex flex-wrap items-center justify-between gap-4 bg-white px-6 py-5 shadow-sm">
        <div>
            <h1 class="text-2xl font-bold text-sky-700">Moses Healthcare</h1>
            <p class="text-sm text-slate-500">Doctor Portal</p>
        </div>
        <a href="../logout.php" class="rounded-lg bg-red-50 px-5 py-3 text-red-600">Logout</a>
    </header>

    <main class="mx-auto max-w-6xl space-y-6 p-6">
        <section>
            <h2 class="text-3xl font-bold">Doctor Dashboard</h2>
            <p class="mt-2 text-slate-500">
                Welcome, <?= escape($_SESSION["full_name"] ?? "Doctor") ?> —
                <?= escape($doctor["specialty"]) ?>
            </p>
        </section>

        <?php if ($success): ?>
            <p class="rounded-lg bg-green-100 p-4 text-green-800"><?= escape($success) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="rounded-lg bg-red-100 p-4 text-red-700"><?= escape($error) ?></p>
        <?php endif; ?>

        <section class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl bg-white p-5 shadow-sm">
                <p class="text-slate-500">Total Appointments</p>
                <p class="mt-2 text-3xl font-bold text-sky-700"><?= count($appointments) ?></p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm">
                <p class="text-slate-500">Pending Requests</p>
                <p class="mt-2 text-3xl font-bold text-amber-500"><?= $pendingCount ?></p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm">
                <p class="text-slate-500">Specialty</p>
                <p class="mt-2 text-xl font-bold"><?= escape($doctor["specialty"]) ?></p>
            </div>
        </section>

        <section class="rounded-xl bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-xl font-bold">Appointment Requests</h3>

            <?php if (!$appointments): ?>
                <p class="text-slate-500">No appointments yet. Patient requests will appear here.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($appointments as $appointment): ?>
                        <article class="rounded-lg border p-4">
                            <div class="flex flex-wrap justify-between gap-2">
                                <h4 class="font-bold"><?= escape($appointment["patient_name"]) ?></h4>
                                <span class="capitalize text-slate-500"><?= escape($appointment["status"]) ?></span>
                            </div>
                            <p class="mt-2 text-sm text-slate-600">
                                <?= escape($appointment["appointment_date"]) ?>
                                at <?= escape(substr($appointment["appointment_time"], 0, 5)) ?>
                            </p>
                            <p class="mt-2">Reason: <?= escape($appointment["reason"]) ?></p>
                            <p class="mt-1 text-sm text-slate-500">
                                Phone: <?= escape($appointment["patient_phone"]) ?>
                            </p>

                            <?php if ($appointment["status"] === "pending"): ?>
                                <form method="POST" class="mt-4 flex flex-wrap gap-3">
                                    <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                    <input type="hidden" name="appointment_id" value="<?= (int)$appointment["id"] ?>">
                                    <button name="status" value="confirmed"
                                        class="rounded-lg bg-green-600 px-4 py-2 text-white">
                                        Confirm
                                    </button>
                                    <button name="status" value="declined"
                                        class="rounded-lg bg-red-600 px-4 py-2 text-white">
                                        Decline
                                    </button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>