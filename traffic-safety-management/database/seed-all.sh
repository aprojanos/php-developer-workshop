php src/seed_projects.php --count=0
php src/seed_hotspots.php --count=0
php src/seed_accidents.php --pdo=0 --injury=0
php src/seed_intersections.php --count=100
php src/seed_road_segments.php --count=50
php src/seed_countermeasures.php --intersection=60 --road=40
php src/seed_accidents.php --pdo=150 --injury=250
php src/seed_hotspots.php --count=12 --intersection-share=0.4
php src/seed_projects.php --count=8 --no-purge