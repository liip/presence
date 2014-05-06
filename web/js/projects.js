$(function() {
    var PlanningSummary = {
        projects: [],
        colours: [
            "#008537",
            "#00C746",
            "#00FF59",

            "#1D00AD",
            "#2B00FF",
            "#5B3BFF",

            "#0086A1",
            "#00AACC",
            "#00CAF2",

            "#B8005F",
            "#FF0084",
            "#FF45A5",

            "#BD0000",
            "#FF0000",
            "#FF3D3D",
            "#FF8000",
            "#CDD400",

            "#850073",
            "#D400B7",
            "#FF24E2"
        ],

        /**
         * Extracts project information and adds it to the entries a data attribute
         */
        calculate: function() {
            var projects = this.projects = [];

            $('li.person .day').each(function(){
                $(this).find('.timeSlotBubble').each(function(){
                    var element = $(this);
                    var project = this.innerHTML.toLowerCase()
                                                .replace(/^\s+|\s+$/g,'')
                                                .replace(/(\s|[-_\.]).+$/, '');

                    if(element.hasClass('hidden') || element.hasClass('off')) { return; }

                    if(element.hasClass('available')) {
                        project = 'available';
                    }
                    if(project === '') {
                        project = 'other';
                    }
                    element.attr('data-project', project);

                    if(project === 'other' || project === 'available') { return; }

                    if($.inArray(project, projects) === -1) {
                        projects.push(project);
                    }

                });
            });

            this.render();
        },

        /**
         * Colorizes and counts the project information
         */
        render: function() {
            var colours = this.colours,
                stats = '', i = 0, l = 0;

            this.projects.sort();
            //colorize elements
            for(i = 0, l = this.projects.length; i < l; i = i + 1) {
                $('*[data-project="'+ this.projects[i]+ '"]')
                    .css({'background-color': colours[i]});
            }

            // Adding an html string once to the dom is fastest to render
            stats += '<span class="timeSlotBubble available">AVAILABLE&nbsp;'+
                        $('*[data-project="available"]').length / 2 +'</span>';
            stats += '<span class="timeSlotBubble busy other">BUSY&nbsp;'+
                        $('*[data-project="other"]').length / 2 +'</span>';
            stats += '<span class="timeSlotBubble hidden">&nbsp;</span>';

            for(i = 0, l = this.projects.length; i < l; i = i + 1) {
                stats += '<span class="timeSlotBubble busy" style="background-color:'+ colours[i]+ ';"> '+
                            this.projects[i].toUpperCase()+ '&nbsp;&nbsp;'+
                                $('*[data-project="'+ this.projects[i] +'"]').length / 2 +
                         '</span>';
            }

            $('.summary .result').html(stats);
        }
    }

    PlanningSummary.calculate();
});