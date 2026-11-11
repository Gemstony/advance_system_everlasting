<?php
// accountant/inc/footer.php
?>
</div> <!-- close .app from header -->
<div id="scrollTopBtn" class="scroll-top-btn"><i class="bi bi-arrow-up"></i></div>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
const scrollBtn = document.getElementById('scrollTopBtn');
window.addEventListener('scroll', () => {
    if (window.scrollY > 300) scrollBtn.classList.add('show');
    else scrollBtn.classList.remove('show');
});
scrollBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
</script>
</body>
</html>