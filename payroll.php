<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payroll</title>

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial;}

body{display:flex;flex-direction:column;min-height:100vh;}

/* HEADER */
.header{
    background:#7c3aed;
    color:white;
    padding:15px 20px;
    display:flex;
    justify-content:space-between;
}

/* MAIN */
.main{display:flex;flex:1;}

/* CONTENT */
.content{
    flex:1;
    padding:20px;
    background:#f5f3ff;
}

/* CARDS */
.cards-container{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:20px;
    margin-bottom:20px;
}

.cards{
    background:white;
    padding:20px;
    border-radius:10px;
    text-align:center;
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
    background:white;
}
th,td{
    padding:10px;
    border-bottom:1px solid #ddd;
}
th{background:#7c3aed;color:white;}

button{
    padding:5px 10px;
    border:none;
    border-radius:5px;
    cursor:pointer;
    color:white;
}

.pay{background:#10b981;}
.edit{background:#3b82f6;}
.delete{background:#ef4444;}
</style>
</head>

<body>

<!-- HEADER -->
<div class="header">
    <a href="admindashboard.php"><button class="back-btn" >⬅ Back</button></a>
    <h1 class="title">Payroll Dashboard</h1>
</div>

<style>
.header {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #26045e;
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

<div class="main">

<div class="content">

<div class="cards-container">
    <div class="cards">Total Salaries: <?php echo $total_salary; ?></div>
    <div class="cards">Paid: <?php echo $paid_salary; ?></div>
    <div class="cards">Unpaid: <?php echo $unpaid_salary; ?></div>
</div>

<h3>Staff Payroll</h3>
<table>
<tr>
<th>ID</th>
<th>Name</th>
<th>Position</th>
<th>Salary</th>
<th>Status</th>
<th>Actions</th>
</tr>

<?php
while($row = $payroll->fetch_assoc()){
    echo "<tr>";
    echo "<td>".$row['id']."</td>";
    echo "<td>".$row['name']."</td>";
    echo "<td>".$row['position']."</td>";
    echo "<td>".$row['salary']."</td>";
    echo "<td>".$row['status']."</td>";
    echo "<td>
        <button class='pay'>Pay</button>
        <button class='edit'>Edit</button>
        <button class='delete'>Delete</button>
    </td>";
    echo "</tr>";
}
?>

</table>

</div>
</div>

</body>
</html>