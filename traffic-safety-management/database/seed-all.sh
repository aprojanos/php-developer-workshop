php database/seed_projects.php --count=0
php database/seed_hotspots.php --count=0
php database/seed_accidents.php --pdo=0 --injury=0
php database/seed_intersections.php --count=100
php database/seed_road_segments.php --count=50
php database/seed_countermeasures.php --intersection=60 --road=40
php database/seed_accidents.php --pdo=150 --injury=250
php database/seed_hotspots.php --count=12 --intersection-share=0.4
php database/seed_projects.php --count=8 --no-purge