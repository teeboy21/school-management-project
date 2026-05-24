<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.html");
    exit();
}

$role = $_SESSION['role'];
$display_name = $_SESSION['fullname'] ?? ucfirst($role);
$display_email = $_SESSION['email'] ?? '';
$dash_map = ['admin'=>'admindashboard.php','student'=>'userdashboard.php','teacher'=>'teacherdashboard.php','hr_manager'=>'hr_dashboard.php','finance_manager'=>'finance_dashboard.php','principal'=>'principal_dashboard.php','it_technician'=>'it_dashboard.php'];
$dashboard_url = $dash_map[$role] ?? 'login.html';

require __DIR__ . '/config.php';

$events_enabled = true;
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'events_enabled'");
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if ($row && $row['setting_value'] === 'off') {
    $events_enabled = false;
}

if ($role === 'admin') {
    $sidebar = [
        ['label' => 'Dashboard', 'href' => 'admindashboard.php'],
        ['label' => 'Students', 'href' => 'student.php'],
        ['label' => 'Teachers', 'href' => 'viewTeacher.php'],
        ['label' => 'Academics', 'href' => 'acedemics.php'],
        ['label' => 'Events', 'href' => 'events.php', 'active' => true],
        ['label' => 'Reports', 'href' => 'reports.php'],
    ];
} elseif ($role === 'principal') {
    $sidebar = [
        ['label' => 'Dashboard', 'href' => 'principal_dashboard.php'],
        ['label' => 'Events', 'href' => 'events.php', 'active' => true],
    ];
} elseif ($role === 'teacher') {
    $sidebar = [
        ['label' => 'Dashboard', 'href' => 'teacherdashboard.php'],
        ['label' => 'My Classes', 'href' => 'myclassesteacher.php'],
        ['label' => 'My Students', 'href' => 'mystudentsteacher.php'],
        ['label' => 'Marks', 'href' => 'marks.php'],
        ['label' => 'Events', 'href' => 'events.php', 'active' => true],
        ['label' => 'Profile', 'href' => 'teacherprofile.php'],
    ];
} else {
    $sidebar = [
        ['label' => 'Dashboard', 'href' => 'userDashboard.php'],
        ['label' => 'My Subjects', 'href' => 'studentsubject.php'],
        ['label' => 'Assignments', 'href' => 'assignments.php'],
        ['label' => 'Results', 'href' => 'results.php'],
        ['label' => 'Events', 'href' => 'events.php', 'active' => true],
        ['label' => 'Profile', 'href' => 'userprofile.php'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Events</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
            <div class="app-header-meta">
                <div class="app-header-title">School Events</div>
                <div class="app-header-subtitle">
                    <?= in_array($role, ['admin', 'principal'])
                        ? 'Create and manage events for the school community.'
                        : 'View upcoming school events and notices.' ?>
                </div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($display_name) ?></div>
                <div class="app-user-email"><?= htmlspecialchars($display_email) ?></div>
            </div>
        </header>

        <section class="app-content">
            <?php if ($events_enabled): ?>
            <div class="hero-card">
                <h2><?= in_array($role, ['admin', 'principal']) ? 'Event Control' : 'Event Calendar' ?></h2>
                <p>
                    <?= in_array($role, ['admin', 'principal'])
                        ? 'Create, manage and oversee events for the school community.'
                        : 'View upcoming school events and notices.' ?>
                </p>
            </div>

            <div class="cards-grid" style="margin-bottom:20px;">
                <div class="stat-card">
                    <div>Total Events</div>
                    <strong id="totalEvents">0</strong>
                </div>
                <div class="stat-card">
                    <div>Upcoming This Month</div>
                    <strong id="upcomingMonth">0</strong>
                </div>
                <div class="stat-card">
                    <div>Audience Match</div>
                    <strong id="audienceMatch">0</strong>
                </div>
            </div>

            <?php if (in_array($role, ['admin', 'principal'])): ?>
                <div class="panel" style="margin-bottom:20px;">
                    <h2>Create Event</h2>
                    <form id="eventForm" class="form-grid">
                        <div>
                            <label for="eventTitle">Title</label>
                            <input type="text" id="eventTitle" required placeholder="School open day">
                        </div>
                        <div>
                            <label for="eventDate">Date</label>
                            <input type="date" id="eventDate" required>
                        </div>
                        <div>
                            <label for="eventTime">Time</label>
                            <input type="time" id="eventTime" required>
                        </div>
                        <div>
                            <label for="eventAudience">Audience</label>
                            <select id="eventAudience" required>
                                <option value="all">All</option>
                                <option value="students">Students</option>
                                <option value="teachers">Teachers</option>
                                <option value="admins">Admins</option>
                            </select>
                        </div>
                        <div class="full">
                            <label for="eventLocation">Location</label>
                            <input type="text" id="eventLocation" placeholder="Main hall">
                        </div>
                        <div class="full">
                            <label for="eventDescription">Description</label>
                            <textarea id="eventDescription" placeholder="Event details, notes, or instructions"></textarea>
                        </div>
                        <div class="page-actions">
                            <button type="submit" class="btn btn-primary" id="eventSubmitBtn">Save Event</button>
                            <button type="button" class="btn btn-secondary" id="eventResetBtn" onclick="if(form) form.reset()">Clear Form</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <div class="panel">
                <div class="toolbar">
                    <h2><?= in_array($role, ['admin', 'principal']) ? 'Manage Events' : 'Upcoming Events' ?></h2>
                    <input class="search-input" type="search" id="eventSearch" placeholder="Search by title, location, audience">
                </div>
                <div id="eventsList" class="cards-grid"></div>
            </div>
            <?php else: ?>
            <div class="hero-card">
                <h2>Events</h2>
                <p>The events module is currently disabled by the administrator.</p>
            </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<script src="assets/toast.js"></script>
<?php if ($events_enabled): ?>
<script>

const role = <?= json_encode($role) ?>;
const searchInput = document.getElementById("eventSearch");
const eventsList = document.getElementById("eventsList");
const totalEventsEl = document.getElementById("totalEvents");
const upcomingMonthEl = document.getElementById("upcomingMonth");
const audienceMatchEl = document.getElementById("audienceMatch");

async function loadEvents() {
    const res = await fetch("API/events_api.php?action=get");
    return await res.json();
}

function isVisibleToRole(event) {
    return event.audience === "all" ||
        event.audience === role;
}

function renderStats(events) {
    const visibleEvents = role === "admin" || role === "principal" ? events : events.filter(isVisibleToRole);
    totalEventsEl.textContent = events.length;

    const now = new Date();
    const month = now.getMonth();
    const year = now.getFullYear();

    const monthCount = visibleEvents.filter(e => {
        const d = new Date(e.event_date);
        return d.getMonth() === month && d.getFullYear() === year;
    }).length;

    upcomingMonthEl.textContent = monthCount;
    audienceMatchEl.textContent = visibleEvents.length;
}

async function renderEvents() {
    const events = await loadEvents();

    renderStats(events);

    const query = (searchInput.value || "").toLowerCase();

    const visible = (role === "admin" || role === "principal" ? events : events.filter(isVisibleToRole))
        .filter(e => {
            const text = `${e.title} ${e.location} ${e.description}`.toLowerCase();
            return text.includes(query);
        });

    if (!visible.length) {
        eventsList.innerHTML = `<div>No events found</div>`;
        return;
    }

    eventsList.innerHTML = visible.map(e => `
        <div class="content-card">
            <h3>${e.title}</h3>
            <p>${e.description || ""}</p>
            <p><strong>Date:</strong> ${e.event_date}</p>
            <p><strong>Time:</strong> ${e.event_time}</p>
            <p><strong>Location:</strong> ${e.location || ""}</p>

            ${role === "admin" || role === "principal" ? `
                <button onclick="deleteEvent(${e.id})">Delete</button>
            ` : ""}
        </div>
    `).join("");
}

async function deleteEvent(id) {
    if (!confirm("Delete this event?")) return;
    const res = await fetch(`API/events_api.php?action=delete&id=${id}`);
    const data = await res.json();
    if (data.status === "deleted") {
        Toast.show("Event deleted successfully", "success");
        renderEvents();
    } else {
        Toast.show(data.message || "Failed to delete event", "error");
    }
}

const form = document.getElementById("eventForm");

if (form) {
    form.addEventListener("submit", async function(e) {
        e.preventDefault();

        const title = document.getElementById("eventTitle").value;
        if (!title) {
            Toast.show("Please enter event title", "error");
            return;
        }

        const payload = {
            title: title,
            date: document.getElementById("eventDate").value,
            time: document.getElementById("eventTime").value,
            audience: document.getElementById("eventAudience").value,
            location: document.getElementById("eventLocation").value,
            description: document.getElementById("eventDescription").value
        };

        const res = await fetch("API/events_api.php?action=save", {
            method: "POST",
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.status === "success") {
            Toast.show("Event saved successfully", "success");
            form.reset();
            renderEvents();
        } else {
            Toast.show(data.message || "Failed to save event", "error");
            console.error('Error:', data);
        }
    });
}

searchInput.addEventListener("input", renderEvents);

renderEvents();

</script>
<?php endif; ?>
</body>
</html>
