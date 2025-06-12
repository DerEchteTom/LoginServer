<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Product Table</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .product-table td:first-child, .product-table th:first-child {
      width: 35%;
      white-space: nowrap;
    }
    .product-table td:nth-child(2), .product-table th:nth-child(2) {
      width: 25%;
    }
    .product-table td:nth-child(3), .product-table th:nth-child(3) {
      width: 20%;
    }
    .product-table td:last-child, .product-table th:last-child {
      width: 20%;
    }
  </style>
</head>
<body>
<div class="container mt-4">
  <h5>Product List</h5>
  <table class="table table-bordered table-sm product-table">
    <thead class="table-light">
      <tr>
        <th>Product Name</th>
        <th>Category</th>
        <th>Price</th>
        <th>Available</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Wireless Mouse</td>
        <td>Accessories</td>
        <td>€19.99</td>
        <td><span class="badge bg-success">Yes</span></td>
      </tr>
      <tr>
        <td>Gaming Keyboard</td>
        <td>Accessories</td>
        <td>€39.99</td>
        <td><span class="badge bg-danger">No</span></td>
      </tr>
    </tbody>
  </table>
</div>
</body>
</html>
