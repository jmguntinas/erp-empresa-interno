        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    var triggers = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    triggers.forEach(function (el) { new bootstrap.Tooltip(el); });
  });
</script>
<style>
  /* Botón icon-only compacto y alineado */
  .btn-icon { 
    width: 32px; height: 32px; 
    display: inline-flex; align-items: center; justify-content: center; 
    padding: 0; 
  }
  .action-gap > * { margin-left: .25rem; }
</style>

</body>
</html>
