# Database Usage Examples

Os exemplos abaixo demonstram comandos SQL comuns utilizados no banco de dados.

```sql
CREATE TABLE customers (
    customerId INT AUTO_INCREMENT PRIMARY KEY,
    customerName VARCHAR(255) NOT NULL,
    customerEmail VARCHAR(255) UNIQUE NOT NULL,
    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

```sql
ALTER TABLE customers
ADD COLUMN statusFlag TINYINT(1) DEFAULT 1;
```

```sql
INSERT INTO customers (customerName, customerEmail)
VALUES ('Maria Oliveira', 'maria.oliveira@example.com');
```

```sql
UPDATE customers
SET statusFlag = 0
WHERE customerEmail = 'maria.oliveira@example.com';
```

```sql
DELETE FROM customers
WHERE statusFlag = 0;
```

```sql
DELETE FROM process_notifications
WHERE processId = 42
  AND alertStatus IN ('Approved', 'Rejected');
```
