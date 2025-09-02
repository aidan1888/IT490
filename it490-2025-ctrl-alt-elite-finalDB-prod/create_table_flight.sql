CREATE TABLE Flights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flight_number VARCHAR(50),
    airline VARCHAR(255),
    origin_icao VARCHAR(10),
    destination_icao VARCHAR(10),
    departure_time DATETIME,
    arrival_time DATETIME,
    real_time_status VARCHAR(100),
    is_round_trip BOOLEAN,
    aircraft_model VARCHAR(255),
    seat_capacity INT,
    registration_date DATE,
    aircraft_age INT,
    FOREIGN KEY (origin_icao) REFERENCES Airports(icao),
    FOREIGN KEY (destination_icao) REFERENCES Airports(icao)
);