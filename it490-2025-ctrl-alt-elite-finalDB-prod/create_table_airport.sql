CREATE TABLE Airports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    icao VARCHAR(10) UNIQUE NOT NULL,
    iata VARCHAR(10),
    name VARCHAR(255),
    city VARCHAR(255),
    country VARCHAR(255),
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    runway_length INT,
    timezone VARCHAR(50)
);