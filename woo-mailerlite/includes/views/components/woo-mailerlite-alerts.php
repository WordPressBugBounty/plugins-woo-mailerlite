<?php if(isset($this->alerts['error'])): ?>
    <div class="woo-ml-alert">
        <span class="woo-ml-closebtn" onclick="this.parentElement.style.display='none';">&times;</span>
        <?php echo $this->alerts['error'] ?>
    </div>
<?php endif; ?>

<?php if(isset($this->alerts['success'])): ?>
    <div class="woo-ml-alert-success">
        <span class="woo-ml-closebtn-success" onclick="this.parentElement.style.display='none';">&times;</span>
        <?php echo $this->alerts['success']; ?>
    </div>
<?php endif; ?>
