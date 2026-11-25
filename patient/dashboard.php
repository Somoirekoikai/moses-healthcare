
<?php
require_once __DIR__ . "/../database.php";
require_once __DIR__ . "/../includes/functions.php";

requireRole("patient");

$patientId = (int) $_SESSION["user_id"];
$error = "";
$success = "";

// Create CSRF token
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Handle appointment booking
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $error = "Invalid request. Please refresh the page.";

    } else {
        $doctorId = filter_input(
            INPUT_POST,
            "doctor_id",
            FILTER_VALIDATE_INT
        );

        $date = $_POST["appointment_date"] ?? "";
        $time = $_POST["appointment_time"] ?? "";
        $reason = trim($_POST["reason"] ?? "");

        $dateObject = DateTime::createFromFormat("!Y-m-d", $date);
        $today = date("Y-m-d");

        if (!$doctorId || !$date || !$time || !$reason) {
            $error = "Please fill in all appointment fields.";

        } elseif (
            !$dateObject ||
            $dateObject->format("Y-m-d") !== $date ||
            $date < $today
        ) {
            $error = "Please select a valid future appointment date.";

        } elseif (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            $error = "Please select a valid appointment time.";

        } elseif (strlen($reason) > 2000) {
            $error = "Your reason must be less than 2000 characters.";

        } else {
            try {
                // Confirm doctor exists and is available
                $check = $pdo->prepare(
                    "SELECT id FROM doctors
                     WHERE id = ? AND availability = 'available'"
                );

                $check->execute([$doctorId]);

                if (!$check->fetch()) {
                    $error = "That doctor is unavailable. Please choose another.";

                } else {
                    // Save appointment
                    $stmt = $pdo->prepare(
                        "INSERT INTO appointments
                        (patient_id, doctor_id, appointment_date,
                         appointment_time, reason)
                        VALUES (?, ?, ?, ?, ?)"
                    );

                    $stmt->execute([
                        $patientId,
                        $doctorId,
                        $date,
                        $time,
                        $reason
                    ]);

                    redirect("dashboard.php?booked=1");
                }

            } catch (PDOException $e) {
                error_log($e->getMessage());
                $error = "Unable to book your appointment. Please try again.";
            }
        }
    }
}

if (isset($_GET["booked"])) {
    $success = "Appointment request submitted successfully!";
}

// Get patient name
$stmt = $pdo->prepare(
    "SELECT full_name FROM users WHERE id = ?"
);
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

$patientName = $patient["full_name"] ?? "Patient";

// Get available doctors
$stmt = $pdo->query(
    "SELECT d.id, d.specialty, u.full_name
     FROM doctors d
     JOIN users u ON d.user_id = u.id
     WHERE d.availability = 'available'
     AND u.role = 'doctor'
     ORDER BY d.specialty, u.full_name"
);

$doctors = $stmt->fetchAll();

// Get appointment statistics
$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'pending') AS pending,
        SUM(status = 'confirmed') AS confirmed
     FROM appointments
     WHERE patient_id = ?"
);

$stmt->execute([$patientId]);
$stats = $stmt->fetch();

// Get appointment history
$stmt = $pdo->prepare(
    "SELECT a.*, u.full_name AS doctor_name, d.specialty
     FROM appointments a
     JOIN doctors d ON a.doctor_id = d.id
     JOIN users u ON d.user_id = u.id
     WHERE a.patient_id = ?
     ORDER BY a.appointment_date DESC, a.appointment_time DESC"
);

$stmt->execute([$patientId]);
$appointments = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Patient Dashboard | Moses Healthcare</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: "Century Gothic", CenturyGothic,
                         AppleGothic, sans-serif;
        }

        .nav-link.active {
            background: #0284c7;
            color: white;
        }

        .nav-link {
            transition: 0.2s;
        }

        .nav-link:hover {
            background: #e0f2fe;
            color: #0369a1;
        }

        .section {
            display: none;
        }

        .section.active {
            display: block;
        }
    </style>
</head>

<body class="bg-sky-50 text-slate-800">

<div class="min-h-screen flex flex-col md:flex-row">

    <!-- SIDEBAR -->
    <aside class="w-full md:w-64 bg-white border-r border-sky-100
                  flex flex-col">

        <div class="p-6 border-b border-sky-100">
            <h1 class="text-xl font-bold text-sky-700">
                ✚ Moses Healthcare
            </h1>

            <p class="text-xs text-gray-500 mt-1">
                Patient Portal
            </p>
        </div>

        <nav class="p-4 space-y-2 flex-1">

            <button onclick="showSection('overview', this)"
                    class="nav-link active w-full text-left px-4 py-3 rounded-lg">
                Dashboard
            </button>

            <button onclick="showSection('booking', this)"
                    class="nav-link w-full text-left px-4 py-3 rounded-lg">
                Book Appointment
            </button>

            <button onclick="showSection('history', this)"
                    class="nav-link w-full text-left px-4 py-3 rounded-lg">
                Appointment History
            </button>

        </nav>

        <div class="p-4 border-t border-sky-100">
            <a href="../logout.php"
               class="block text-center bg-red-50 text-red-600
                      hover:bg-red-100 px-4 py-3 rounded-lg">
                Logout
            </a>
        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <div class="flex-1 flex flex-col min-w-0">

        <!-- HEADER -->
        <header class="bg-white border-b border-sky-100
                       px-6 py-5 flex flex-wrap items-center
                       justify-between gap-3">

            <div>
                <h2 class="text-xl font-bold">
                    Patient Dashboard
                </h2>

                <p class="text-sm text-gray-500">
                    Welcome back, <?= escape($patientName) ?>
                </p>
            </div>

            <div class="bg-sky-100 text-sky-700 rounded-full
                        px-4 py-2 text-sm font-semibold">
                Patient
            </div>

        </header>

        <main class="p-4 md:p-8 flex-1">

            <?php if ($error !== ""): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6">
                    <?= escape($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-6">
                    <?= escape($success) ?>
                </div>
            <?php endif; ?>

            <!-- OVERVIEW -->
            <section id="overview" class="section active">

                <div class="mb-6">
                    <h2 class="text-2xl font-bold">Overview</h2>

                    <p class="text-gray-500 mt-1">
                        Your healthcare activity at a glance.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-sky-100">
                        <p class="text-gray-500 text-sm">Total Appointments</p>

                        <p class="text-3xl font-bold text-sky-700 mt-3">
                            <?= (int) ($stats["total"] ?? 0) ?>
                        </p>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-sky-100">
                        <p class="text-gray-500 text-sm">Pending Requests</p>

                        <p class="text-3xl font-bold text-amber-500 mt-3">
                            <?= (int) ($stats["pending"] ?? 0) ?>
                        </p>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm border border-sky-100">
                        <p class="text-gray-500 text-sm">Confirmed Appointments</p>

                        <p class="text-3xl font-bold text-green-600 mt-3">
                            <?= (int) ($stats["confirmed"] ?? 0) ?>
                        </p>
                    </div>

                </div>

                <div class="mt-8 bg-white p-6 rounded-xl shadow-sm">
                    <h3 class="text-lg font-bold mb-2">
                        Welcome to Moses Healthcare
                    </h3>

                    <p class="text-gray-600 text-sm">
                        Book an appointment with an available doctor
                        and monitor your appointment requests here.
                    </p>

                    <button onclick="showSection('booking')"
                            class="mt-5 bg-sky-600 hover:bg-sky-700
                                   text-white px-5 py-3 rounded-lg">
                        Book an Appointment
                    </button>
                </div>

            </section>

            <!-- BOOKING -->
            <section id="booking" class="section">

                <div class="mb-6">
                    <h2 class="text-2xl font-bold">Book Appointment</h2>

                    <p class="text-gray-500 mt-1">
                        Select a specialty and available doctor.
                    </p>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6 max-w-3xl">

                    <?php if (empty($doctors)): ?>

                        <p class="text-gray-600">
                            No doctors are currently available.
                            Please check again later.
                        </p>

                    <?php else: ?>

                        <form method="POST" class="space-y-5">

                            <input type="hidden" name="csrf_token"
                                   value="<?= escape($_SESSION["csrf_token"]) ?>">

                            <div>
                                <label class="block text-sm font-semibold mb-2">
                                    Medical Specialty
                                </label>

                                <select id="specialty" required
                                        class="w-full border border-sky-200 rounded-lg
                                               px-4 py-3 focus:ring-2 focus:ring-sky-400">

                                    <option value="">Select specialty</option>

                                    <?php
                                    $specialties = array_unique(
                                        array_column($doctors, "specialty")
                                    );

                                    foreach ($specialties as $specialty):
                                    ?>
                                        <option value="<?= escape($specialty) ?>">
                                            <?= escape($specialty) ?>
                                        </option>
                                    <?php endforeach; ?>

                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2">
                                    Available Doctor
                                </label>

                                <select name="doctor_id" id="doctor_id" required
                                        class="w-full border border-sky-200 rounded-lg
                                               px-4 py-3 focus:ring-2 focus:ring-sky-400">

                                    <option value="">Select a specialty first</option>

                                    <?php foreach ($doctors as $doctor): ?>

                                        <option
                                            value="<?= (int) $doctor["id"] ?>"
                                            data-specialty="<?= escape($doctor["specialty"]) ?>"
                                            hidden>

                                            <?= escape($doctor["full_name"]) ?>
                                            — <?= escape($doctor["specialty"]) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                                <div>
                                    <label class="block text-sm font-semibold mb-2">
                                        Appointment Date
                                    </label>

                                    <input type="date" name="appointment_date"
                                           min="<?= date("Y-m-d") ?>" required
                                           class="w-full border border-sky-200 rounded-lg
                                                  px-4 py-3">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold mb-2">
                                        Preferred Time
                                    </label>

                                    <input type="time" name="appointment_time" required
                                           class="w-full border border-sky-200 rounded-lg
                                                  px-4 py-3">
                                </div>

                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2">
                                    Reason for Appointment
                                </label>

                                <textarea name="reason" rows="4" required
                                          maxlength="2000"
                                          placeholder="Briefly describe why you need an appointment..."
                                          class="w-full border border-sky-200 rounded-lg
                                                 px-4 py-3"></textarea>
                            </div>

                            <button type="submit"
                                    class="bg-sky-600 hover:bg-sky-700 text-white
                                           font-semibold px-6 py-3 rounded-lg">
                                Submit Appointment Request
                            </button>

                        </form>

                    <?php endif; ?>

                </div>

            </section>

            <!-- APPOINTMENT HISTORY -->
            <section id="history" class="section">

                <div class="mb-6">
                    <h2 class="text-2xl font-bold">Appointment History</h2>

                    <p class="text-gray-500 mt-1">
                        View your appointment requests and their status.
                    </p>
                </div>

                <div class="bg-white rounded-xl shadow-sm overflow-x-auto">

                    <table class="w-full text-sm text-left">

                        <thead class="bg-sky-100 text-sky-800">
                            <tr>
                                <th class="p-4">Doctor</th>
                                <th class="p-4">Specialty</th>
                                <th class="p-4">Date</th>
                                <th class="p-4">Time</th>
                                <th class="p-4">Reason</th>
                                <th class="p-4">Status</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y">

                            <?php if (empty($appointments)): ?>

                                <tr>
                                    <td colspan="6" class="p-6 text-center text-gray-500">
                                        You have no appointments yet.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($appointments as $appointment): ?>

                                    <tr class="hover:bg-sky-50">

                                        <td class="p-4 font-medium">
                                            <?= escape($appointment["doctor_name"]) ?>
                                        </td>

                                        <td class="p-4">
                                            <?= escape($appointment["specialty"]) ?>
                                        </td>

                                        <td class="p-4">
                                            <?= escape($appointment["appointment_date"]) ?>
                                        </td>

                                        <td class="p-4">
                                            <?= escape(substr($appointment["appointment_time"], 0, 5)) ?>
                                        </td>

                                        <td class="p-4 max-w-xs">
                                            <?= escape($appointment["reason"]) ?>
                                        </td>

                                        <td class="p-4">

                                            <?php
                                            $status = $appointment["status"];

                                            $statusClass = [
                                                "pending" => "bg-amber-100 text-amber-700",
                                                "confirmed" => "bg-green-100 text-green-700",
                                                "declined" => "bg-red-100 text-red-700"
                                            ][$status] ?? "bg-gray-100 text-gray-700";
                                            ?>

                                            <span class="inline-block px-3 py-1 rounded-full
                                                         text-xs font-semibold <?= $statusClass ?>">
                                                <?= escape(ucfirst($status)) ?>
                                            </span>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        </main>

        <!-- FOOTER -->
        <footer class="bg-white border-t border-sky-100
                       text-center text-sm text-gray-500 p-5">

            &copy; <?= date("Y") ?> Moses Healthcare.
            All rights reserved.

        </footer>

    </div>

</div>

<script>
    // Dashboard section navigation
    function showSection(sectionId, button = null) {

        document.querySelectorAll(".section").forEach(section => {
            section.classList.remove("active");
        });

        document.getElementById(sectionId).classList.add("active");

        document.querySelectorAll(".nav-link").forEach(link => {
            link.classList.remove("active");
        });

        if (button) {
            button.classList.add("active");
        } else {
            document.querySelectorAll(".nav-link").forEach(link => {
                if (link.getAttribute("onclick").includes(sectionId)) {
                    link.classList.add("active");
                }
            });
        }
    }

    // Filter doctors based on selected specialty
    const specialtySelect = document.getElementById("specialty");
    const doctorSelect = document.getElementById("doctor_id");

    if (specialtySelect && doctorSelect) {

        specialtySelect.addEventListener("change", function () {

            const selectedSpecialty = this.value;

            doctorSelect.value = "";

            doctorSelect.querySelectorAll("option").forEach(option => {

                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                option.hidden =
                    option.dataset.specialty !== selectedSpecialty;
            });

            if (!selectedSpecialty) {
                doctorSelect.options[0].text = "Select a specialty first";
            } else {
                doctorSelect.options[0].text = "Select a doctor";
            }
        });
    }
</script>

</body>
</html>
