INSERT INTO Roles(id, name, is_active) VALUES(-2, 'Manager', 1) ON DUPLICATE KEY UPDATE name = name;
