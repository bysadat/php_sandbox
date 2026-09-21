<?php

function printHello(){
    echo("Hello World\n");
}

printHello();

//Functions With Arguments 
// Argument Type declaration ==> bool, int, float, string, array, object, callable, iterable
function calculateAverage(int $item1,int $itme2,int $item3){
    $avg = ($item1 + $itme2 + $item3)/3;
    echo("The average is : $avg \n");
}

calculateAverage(10,20,100);
//Using named arguments to override the order of the arguments.
calculateAverage(item1: 10, itme2: 20, item3: 100);