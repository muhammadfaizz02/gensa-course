<?php
include '../../config/database.php';

if (isset($_GET['id'])) {
    $semester_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    $query = "SELECT * FROM master_semester WHERE id = $semester_id";
    $result = mysqli_query($conn, $query);
    $semester = mysqli_fetch_assoc($result);
    
    if ($semester) {
        ?>
        <form method="POST" action="../ajax/update_semester.php" class="space-y-4">
            <input type="hidden" name="semester_id" value="<?php echo $semester['id']; ?>">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Semester Name *</label>
                <input type="text" name="nama_semester" required 
                       value="<?php echo htmlspecialchars($semester['nama_semester']); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Academic Year *</label>
                <input type="text" name="tahun_ajaran" required 
                       value="<?php echo htmlspecialchars($semester['tahun_ajaran']); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date *</label>
                    <input type="date" name="tanggal_mulai" required 
                           value="<?php echo $semester['tanggal_mulai']; ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Date *</label>
                    <input type="date" name="tanggal_selesai" required 
                           value="<?php echo $semester['tanggal_selesai']; ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
            </div>
            
            <div>
                <label class="flex items-center">
                    <input type="checkbox" name="status" value="active" 
                           <?php echo $semester['status'] == 'active' ? 'checked' : ''; ?>
                           class="h-4 w-4 text-yellow-600">
                    <span class="ml-2 text-sm text-gray-700">Set as active semester</span>
                </label>
                <p class="text-xs text-gray-500 mt-1">
                    Note: Activating this semester will deactivate all other semesters
                </p>
            </div>
            
            <div class="flex space-x-3 pt-4">
                <button type="button" onclick="closeEditModal()" 
                        class="flex-1 py-2 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 py-2 bg-yellow-500 text-white hover:bg-yellow-600 rounded-lg">
                    Update Semester
                </button>
            </div>
        </form>
        
        <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        });
        </script>
        <?php
    }
}
?>