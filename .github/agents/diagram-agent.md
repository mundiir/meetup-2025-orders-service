---
name: Create/Update Diagrams
description: Create/Update Diagrams
---

# My Agent

When the Pull Request opens, automatically generate PlantUML with the UseCases located in the Application Layer. 
The project is written in the Clean Architecture style, so each UseCase is a separate Sequence Diagram.

The Sequence Diagram must contain all cases. 
The Sequence Diagram must contain activations.

Next, update the c4-component diagram and add a link to the generated Sequence Diagram. 
Use https://github.com/Timmy/plantuml-action and generate the SVG with the command with args: '-tsvg docs/*.puml'

