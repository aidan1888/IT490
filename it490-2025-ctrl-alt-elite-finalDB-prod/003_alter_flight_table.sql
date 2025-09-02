ALTER TABLE Flights   
    ADD UNIQUE KEY uniq_flight_departure (flight_number, departure_time);