<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Enter Marks</title>

</head>
<style>
    body {
    font-family: Arial;
    background: #f4f6f9;
}

.container {
    padding: 20px;
}

table {
    width: 100%;
    background: white;
    border-collapse: collapse;
}

th, td {
    padding: 12px;
    border-bottom: 1px solid #ccc;
}

th {
    background: #34495e;
    color: white;
}

input {
    padding: 8px;
    width: 80px;
}

button {
    padding: 6px 12px;
    background: #27ae60;
    color: white;
    border: none;
    cursor: pointer;
}
</style>
<body>
<!-- HEADER -->
<div class="header">
    <a href="teacherdashboard.php"><button class="back-btn" >⬅ Back</button></a>
    <h1 class="title">Page Title</h1>
</div>

<style>
.header {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #2a5298;
    color: white;
    padding: 15px;
    position: relative;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    padding-bottom: 30px;
}

.back-btn {
    position: absolute;
    left: 15px;
    background: white;
    color: #2a5298;
    border: none;
    padding: 8px 12px;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
   
}

.back-btn:hover {
    background: #ddd;
}

.title {
    margin: 0;
    font-size: 20px;
}
</style>
<div class="container">
    <h2>📝 Enter Marks</h2>

    <table>
        <tr>
            <th>Student Name</th>
            <th>Subject</th>
            <th>Marks</th>
            <th>Action</th>
        </tr>

        <tr>
            <td>John Doe</td>
            <td>Mathematics</td>
            <td><input type="number" placeholder="Enter marks"></td>
            <td><button>Save</button></td>
        </tr>

        <tr>
            <td>Jane Smith</td>
            <td>Mathematics</td>
            <td><input type="number"></td>
            <td><button>Save</button></td>
        </tr>

    </table>
</div>

</body>
</html>