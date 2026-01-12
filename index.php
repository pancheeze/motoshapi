<?php
session_start();
require_once 'config/database.php';
require_once 'config/currency.php';

$title = 'Motoshapi - Motorcycle Parts';
$activePage = 'home';
$mainClass = 'flex-grow-1 p-0';
$renderGlobalFooter = false;
include 'includes/header.php';
?>

    <!-- Modern Hero Section -->
    <div class="modern-hero">
        <div class="modern-hero-overlay"></div>
        <div id="heroSlideshow" class="carousel slide h-100" data-bs-ride="carousel" data-bs-interval="4000">
            <div class="carousel-inner h-100">
                <div class="carousel-item active h-100">
                    <img src="uploads/slideshow/image1.jpeg" class="d-block w-100 h-100" style="object-fit: cover;" alt="Slide 1">
                </div>
                <div class="carousel-item h-100">
                    <img src="uploads/slideshow/image2.jpeg" class="d-block w-100 h-100" style="object-fit: cover;" alt="Slide 2">
                </div>
                <div class="carousel-item h-100">
                    <img src="uploads/slideshow/image3.jpeg" class="d-block w-100 h-100" style="object-fit: cover;" alt="Slide 3">
                </div>
                <div class="carousel-item h-100">
                    <img src="uploads/slideshow/image4.jpeg" class="d-block w-100 h-100" style="object-fit: cover;" alt="Slide 4">
                </div>
                <div class="carousel-item h-100">
                    <img src="uploads/slideshow/image5.jpg" class="d-block w-100 h-100" style="object-fit: cover;" alt="Slide 5">
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#heroSlideshow" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroSlideshow" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#heroSlideshow" data-bs-slide-to="0" class="active"></button>
                <button type="button" data-bs-target="#heroSlideshow" data-bs-slide-to="1"></button>
                <button type="button" data-bs-target="#heroSlideshow" data-bs-slide-to="2"></button>
                <button type="button" data-bs-target="#heroSlideshow" data-bs-slide-to="3"></button>
                <button type="button" data-bs-target="#heroSlideshow" data-bs-slide-to="4"></button>
            </div>
        </div>
        <div class="modern-hero-content">
            <h1 class="modern-hero-title">Premium <span class="modern-accent-text">Parts</span> for Every Rider</h1>
            <p class="modern-hero-subtitle">Driven by Parts. Built for Performance.</p>
            <a href="products.php" class="modern-btn modern-btn-primary modern-btn-lg">Shop Now</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="modern-container modern-section">
        <!-- Welcome Section -->
        <div style="text-align: center; max-width: 800px; margin: 0 auto var(--spacing-2xl);">
            <h2 style="font-size: 2rem; font-weight: 700; margin-bottom: var(--spacing-md);">Welcome to Motoshapi</h2>
            <p style="font-size: 1.125rem; color: var(--text-secondary); margin-bottom: var(--spacing-md);">Your one-stop shop for premium motorcycle parts and accessories.</p>
            <hr style="border-color: var(--border-primary); margin: var(--spacing-lg) auto; max-width: 200px;">
            <p style="color: var(--text-muted);">Browse our extensive collection of high-quality motorcycle parts designed for performance and durability.</p>
        </div>

        <!-- Featured Products -->
        <h2 class="modern-section-title">Featured <span class="modern-accent-text">Products</span></h2>
        <div id="featuredCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="3000">
            <div class="carousel-inner">
                <?php
                $stmt = $conn->query("SELECT * FROM products WHERE featured = 1 ORDER BY created_at DESC LIMIT 4");
                $active = true;
                while($product = $stmt->fetch(PDO::FETCH_ASSOC)):
                ?>
                <div class="carousel-item<?php if($active){echo ' active'; $active = false;} ?>">
                    <div class="d-flex justify-content-center">
                        <div class="modern-card" style="width: 100%; max-width: 400px;">
                            <div style="position: relative; overflow: hidden;">
                                <a href="product.php?id=<?php echo $product['id']; ?>">
                                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" class="modern-card-img" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                </a>
                                <span class="modern-card-badge" style="position: absolute; top: 1rem; right: 1rem;">Featured</span>
                            </div>
                            <div class="modern-card-body">
                                <h3 class="modern-card-title">
                                    <a href="product.php?id=<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></a>
                                </h3>
                                <p class="modern-card-price"><?php echo format_price($product['price']); ?></p>
                                <a href="product.php?id=<?php echo $product['id']; ?>" class="modern-btn modern-btn-primary" style="width: 100%;">View Details</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#featuredCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#featuredCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </div>

    <!-- Footer -->
    <footer class="modern-footer">
        <div class="modern-container">
            <div class="d-flex justify-content-center align-items-center flex-column flex-md-row gap-3">
                <p class="mb-0" style="color: var(--text-secondary);">&copy; 2024 Motoshapi. All rights reserved.</p>
                <button id="aboutUsBtn" class="modern-btn modern-btn-secondary">About Us</button>
            </div>
        </div>
    </footer>

        <!-- About Us Modal -->
        <div class="modal fade" id="aboutUsModal" tabindex="-1" aria-labelledby="aboutUsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-primary);">
                  <div class="modal-header" style="border-bottom: 1px solid var(--border-primary);">
                    <h2 class="modal-title w-100 text-center" id="aboutUsModalLabel" style="color: var(--text-primary);">About Us</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
                  </div>
                  <div class="modal-body">
                    <div class="row justify-content-center gx-5">
                      <?php
                      $about_stmt = $conn->query("SELECT * FROM about_us ORDER BY id ASC");
                      while($member = $about_stmt->fetch(PDO::FETCH_ASSOC)):
                      ?>
                      <div class="col-md-4 col-lg-3 mb-4 d-flex justify-content-center">
                        <div class="modern-card text-center" style="min-width:280px; padding:25px; margin: 0 15px;">
                          <?php if($member['photo_url']): ?>
                            <img src="<?php echo htmlspecialchars($member['photo_url']); ?>" class="mx-auto d-block rounded-circle" alt="<?php echo htmlspecialchars($member['name']); ?>" style="width:200px; height:200px; object-fit:cover; margin-bottom:20px; border: 3px solid var(--accent-primary);">
                          <?php else: ?>
                            <div class="mx-auto d-flex align-items-center justify-content-center rounded-circle" style="width:200px; height:200px; margin-bottom:20px; background: var(--bg-tertiary); color: var(--text-secondary); font-size: 4rem; font-weight: 700;">?</div>
                          <?php endif; ?>
                          <div class="px-3">
                            <h4 class="fw-bold mb-3" style="color: var(--text-primary);"><?php echo htmlspecialchars($member['name']); ?></h4>
                            <p class="mb-2" style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.6;"><?php echo htmlspecialchars($member['description']); ?></p>
                          </div>
                        </div>
                      </div>
                      <?php endwhile; ?>
                    </div>
                  </div>
                </div>
            </div>
        </div>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function() {
        var aboutUsBtn = document.getElementById('aboutUsBtn');
        var aboutUsModal = new bootstrap.Modal(document.getElementById('aboutUsModal'));
        if (aboutUsBtn) {
            aboutUsBtn.addEventListener('click', function() {
              aboutUsModal.show();
            });
        }
      });
    </script>
    <?php include 'includes/footer.php'; ?>