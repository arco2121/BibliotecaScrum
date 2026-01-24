window.onload = function () {
    let dragged;
    const sources = document.querySelectorAll(".choise");
    sources.forEach(source => {
        source.addEventListener("drag", (event) => {
            console.log("dragging");
        });

        source.addEventListener("dragstart", (event) => {
            dragged = event.target;
            event.target.classList.add("dragging");
        });

        source.addEventListener("dragend", (event) => {
            event.target.classList.remove("dragging");
        });
    })

    const targets = document.querySelectorAll(".tile");
    targets.forEach(target => {
        target.addEventListener("dragover", (event) => {
            event.preventDefault();
        });

        target.addEventListener("dragenter", (event) => {
            if (event.target.classList.contains("tile")) {
                event.target.classList.add("dragover");
            }
        });

        target.addEventListener("dragleave", (event) => {
            if (event.target.classList.contains("tile")) {
                event.target.classList.remove("dragover");
            }
        });

        target.addEventListener("drop", (event) => {
            event.preventDefault();
            if (event.target.classList.contains("tile")) {
                event.target.classList.remove("dragover");
                event.target.appendChild(dragged);
            }
        });
    });

    document.getElementById("verify").addEventListener("click",() => {

    })
}