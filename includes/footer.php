<script>
document.addEventListener('DOMContentLoaded', function () {
 const toggle = document.getElementById('menuToggle');
 const sidebar = document.getElementById('sidebar');
 if (toggle && sidebar) toggle.addEventListener('click', function () {
  sidebar.classList.toggle('open');
 });
});
</script>
</body>
</html>
