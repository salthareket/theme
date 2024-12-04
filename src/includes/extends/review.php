<?php

class Review extends Timber\Comment{
    public function get_rating(){
        return $this->meta("rating");
    }
}

/*
function timber_comment($id){
    return new TimberComment($id, "Review");
}
*/