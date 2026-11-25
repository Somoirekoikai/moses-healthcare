<?php
require_once __DIR__ . "/../database.php";
require_once __DIR__ . "/../includes/functions.php";

requireRole("admin");

$success = "";
$error = "";

$csrfToken = $_SESSION["csrf_token"] ?? bin2hex(random_bytes(32));
$_SESSION["csrf_token"] = $csrfToken;

/* Add a doctor */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        $error = "Invalid request. Please refresh and try again.";
    } else {
        $name = trim($_POST["full_name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $phone = trim($_POST["phone"] ?? "");
        $specialty = trim($_POST["specialty"] ?? "");
        $password = $_POST["password"] ?? "";

        if (
            $name === "" ||
            $email === "" ||
            $phone === "" ||
            $specialty === "" ||
            strlen($password) < 8
        ) {
            $error = "Fill in all fields. Password must be at least 8 characters.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Enter a valid email address.";
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "INSERT INTO users (full_name, email, phone, password, role)
                     VALUES (?, ?, ?, ?, 'doctor')"
                );

                $stmt->execute([
                    $name,
                    $email,
                    $phone,
                    password_hash($password, PASSWORD_DEFAULT)
                ]);

                $userId = $pdo->lastInsertId();

                $stmt = $pdo->prepare(
                    "INSERT INTO doctors (user_id, specialty)
                     VALUES (?, ?)"
                );

                $stmt->execute([$userId, $specialty]);

                $pdo->commit();
                $success = "Doctor added successfully.";
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getCode() === "23000"
                    ? "That email address is already registered."
                    : "Could not add doctor. Check your database setup.";
            }
        }
    }
}

/* Dashboard counts */
$patientCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role = 'patient'"
)->fetchColumn();

$doctorCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM doctors"
)->fetchColumn();

$appointmentCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM appointments"
)->fetchColumn();

$pendingCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM appointments WHERE status = 'pending'"
)->fetchColumn();

/* Registered doctors */
$doctors = $pdo->query(
    "SELECT users.full_name, users.email, users.phone,
            doctors.specialty, doctors.availability
     FROM doctors
     JOIN users ON users.id = doctors.user_id
     ORDER BY users.full_name"
)->fetchAll();

/* Appointment overview */
$appointments = $pdo->query(
    "SELECT appointments.appointment_date,
            appointments.appointment_time,
            appointments.reason,
            appointments.status,
            patient.full_name AS patient_name,
            doctor_user.full_name AS doctor_name
     FROM appointments
     JOIN users AS patient ON patient.id = appointments.patient_id
     JOIN doctors ON doctors.id = appointments.doctor_id
     JOIN users AS doctor_user ON doctor_user.id = doctors.user_id
     ORDER BY appointments.appointment_date DESC,
              appointments.appointment_time DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Moses Healthcare</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: "Century Gothic", sans-serif;
        }
    </style>
</head>

<body class="min-h-screen bg-sky-50 text-slate-800">

<header class="flex flex-wrap items-center justify-between gap-4 bg-white px-6 py-5 shadow-sm">
    <div>
        <h1 class="text-2xl font-bold text-sky-700">Moses Healthcare</h1>
        <p class="text-sm text-slate-500">Admin Portal</p>
    </div>

    <a href="../logout.php"
       class="rounded-lg bg-red-50 px-5 py-3 text-red-600">
        Logout
    </a>
</header>

<main class="mx-auto max-w-7xl space-y-8 p-6">

    <section>
        <h2 class="text-3xl font-bold">Admin Dashboard</h2>
        <p class="mt-2 text-slate-500">
            Monitor healthcare activity and manage doctors.
        </p>
    </section>

    <?php if ($success): ?>
        <p class="rounded-lg bg-green-100 p-4 text-green-800">
            <?= escape($success) ?>
        </p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="rounded-lg bg-red-100 p-4 text-red-700">
            <?= escape($error) ?>
        </p>
    <?php endif; ?>

    <!-- System overview -->
    <section>
        <h3 class="mb-4 text-xl font-bold">System Overview</h3>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl bg-white p-6 shadow-sm">
                <p class="text-slate-500">Registered Patients</p>
                <p class="mt-2 text-3xl font-bold text-sky-700">
                    <?= $patientCount ?>
                </p>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm">
                <p class="text-slate-500">Registered Doctors</p>
                <p class="mt-2 text-3xl font-bold text-sky-700">
                    <?= $doctorCount ?>
                </p>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm">
                <p class="text-slate-500">Total Appointments</p>
                <p class="mt-2 text-3xl font-bold text-sky-700">
                    <?= $appointmentCount ?>
                </p>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm">
                <p class="text-slate-500">Pending Requests</p>
                <p class="mt-2 text-3xl font-bold text-amber-500">
                    <?= $pendingCount ?>
                </p>
            </div>
        </div>
    </section>

    <!-- Add doctor and doctor list -->
    <section class="grid gap-6 lg:grid-cols-2">

        <div class="rounded-xl bg-white p-6 shadow-sm">
            <h3 class="mb-5 text-xl font-bold">Add a Doctor</h3>

            <form method="POST" class="space-y-4">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($csrfToken) ?>"
                >

                <div>
                    <label for="full_name" class="mb-1 block">Full Name</label>
                    <input
                        id="full_name"
                        name="full_name"
                        required
                        class="w-full rounded-lg border p-3"
                    >
                </div>

                <div>
                    <label for="email" class="mb-1 block">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        class="w-full rounded-lg border p-3"
                    >
                </div>

                <div>
                    <label for="phone" class="mb-1 block">Phone</label>
                    <input
                        id="phone"
                        name="phone"
                        required
                        class="w-full rounded-lg border p-3"
                    >
                </div>

                <div>
                    <label for="specialty" class="mb-1 block">Specialty</label>
                    <input
                        id="specialty"
                        name="specialty"
                        placeholder="e.g. General Medicine"
                        required
                        class="w-full rounded-lg border p-3"
                    >
                </div>

                <div>
                    <label for="password" class="mb-1 block">Temporary Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        required
                        class="w-full rounded-lg border p-3"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-sky-600 px-5 py-3 font-semibold text-white hover:bg-sky-700"
                >
                    Add Doctor
                </button>
            </form>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm">
            <h3 class="mb-5 text-xl font-bold">
                Registered Doctors (<?= count($doctors) ?>)
            </h3>

            <?php if (!$doctors): ?>
                <p class="text-slate-500">No doctors have been added yet.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($doctors as $doctor): ?>
                        <article class="rounded-lg border p-4">
                            <p class="font-semibold">
                                <?= escape($doctor["full_name"]) ?>
                            </p>
                            <p class="text-sm text-slate-500">
                                <?= escape($doctor["specialty"]) ?>
                            </p>
                            <p class="text-sm text-slate-500">
                                <?= escape($doctor["email"]) ?>
                            </p>
                            <p class="text-sm text-slate-500">
                                Availability: <?= escape($doctor["availability"]) ?>
                            </p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Appointment monitoring -->
    <section class="rounded-xl bg-white p-6 shadow-sm">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-xl font-bold">All Appointments</h3>
            <span class="rounded-full bg-sky-100 px-4 py-2 text-sm text-sky-700">
                <?= count($appointments) ?> total
            </span>
        </div>

        <?php if (!$appointments): ?>
            <p class="text-slate-500">No appointments have been booked yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[700px] border-collapse text-left">
                    <thead>
                        <tr class="border-b text-sm text-slate-500">
                            <th class="p-3">Patient</th>
                            <th class="p-3">Doctor</th>
                            <th class="p-3">Date</th>
                            <th class="p-3">Time</th>
                            <th class="p-3">Reason</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr class="border-b last:border-0">
                                <td class="p-3 font-medium">
                                    <?= escape($appointment["patient_name"]) ?>
                                </td>
                                <td class="p-3">
                                    <?= escape($appointment["doctor_name"]) ?>
                                </td>
                                <td class="p-3">
                                    <?= escape($appointment["appointment_date"]) ?>
                                </td>
                                <td class="p-3">
                                    <?= escape(substr($appointment["appointment_time"], 0, 5)) ?>
                                </td>
                                <td class="p-3">
                                    <?= escape($appointment["reason"]) ?>
                                </td>
                                <td class="p-3">
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm capitalize">
                                        <?= escape($appointment["status"]) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</main>
</body>
</html>